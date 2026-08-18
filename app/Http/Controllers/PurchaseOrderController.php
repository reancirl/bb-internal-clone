<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\TakeoffLine;
use App\Models\Vendor;
use App\Support\TakeoffCosting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Project $project): Response
    {
        $project->load('takeoffLines.priceItem:id,fast_price,material_cost');
        $costing = new TakeoffCosting;
        $dimensions = $project->dimensionValues();

        // Takeoff lines with computed qty/price feed the "new PO" dialog so
        // items prefill from the estimate.
        $takeoffOptions = $project->takeoffLines->map(function (TakeoffLine $line) use ($costing, $dimensions) {
            $calc = $costing->computeLine($line, $dimensions);

            return [
                'id' => $line->id,
                'category' => $line->category,
                'item' => $line->item,
                'unit' => $line->unit,
                'qty' => $calc['qty'],
                'unit_price_cents' => TakeoffCosting::toCents($calc['unit_price']),
                'supplier_id' => $line->supplier_id,
                'ordered' => $line->ordered,
                'on_site' => $line->on_site,
            ];
        })->values();

        $orders = $project->purchaseOrders()
            ->with(['items', 'createdBy:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseOrder $po) => $this->toRow($po));

        $committed = $project->purchaseOrders()
            ->whereIn('status', PurchaseOrder::COMMITTED_STATUSES)
            ->sum('total_cents');

        return Inertia::render('projects/purchase-orders', [
            'project' => $project->only(['id', 'name', 'client_name']),
            'orders' => $orders,
            'takeoffOptions' => $takeoffOptions,
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name', 'type']),
            'committedCents' => (int) $committed,
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validateData($request);
        $vendor = Vendor::findOrFail($data['vendor_id']);
        $rows = $this->itemRows($data['items'], $project);

        $po = retry(3, fn () => DB::transaction(function () use ($project, $request, $data, $vendor, $rows) {
            $po = $project->purchaseOrders()->create([
                'number' => PurchaseOrder::nextNumber((int) now()->format('Y')),
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'total_cents' => array_sum(array_map(fn ($r) => $r['total_cents'] ?? 0, $rows)),
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);
            $po->items()->createMany($rows);

            return $po;
        }), 100);

        return back()->with('success', "{$po->number} created as draft.");
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return back()->with('error', 'Only draft purchase orders can be edited.');
        }

        $data = $this->validateData($request);
        $vendor = Vendor::findOrFail($data['vendor_id']);
        $rows = $this->itemRows($data['items'], $purchaseOrder->project);

        DB::transaction(function () use ($purchaseOrder, $data, $vendor, $rows) {
            $purchaseOrder->update([
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'total_cents' => array_sum(array_map(fn ($r) => $r['total_cents'] ?? 0, $rows)),
                'expected_delivery' => $data['expected_delivery'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($rows);
        });

        return back()->with('success', "{$purchaseOrder->number} updated.");
    }

    public function transition(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
            'expected_delivery' => ['nullable', 'date'],
        ]);

        if (! $purchaseOrder->canTransitionTo($data['status'])) {
            return back()->with('error', "Cannot move a {$purchaseOrder->status} purchase order to {$data['status']}.");
        }

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update(array_merge(
                ['status' => $data['status']],
                isset($data['expected_delivery']) ? ['expected_delivery' => $data['expected_delivery']] : [],
                match ($data['status']) {
                    PurchaseOrder::STATUS_SENT => ['sent_at' => now()],
                    PurchaseOrder::STATUS_CONFIRMED => ['confirmed_at' => now()],
                    PurchaseOrder::STATUS_RECEIVED => ['received_at' => now()],
                    default => [],
                },
            ));

            // Ordering status flows through to the takeoff: sending a PO marks
            // its linked lines ordered; receiving marks them on site.
            $lineIds = $purchaseOrder->items()->whereNotNull('takeoff_line_id')->pluck('takeoff_line_id');
            if ($lineIds->isNotEmpty()) {
                if ($data['status'] === PurchaseOrder::STATUS_SENT) {
                    TakeoffLine::whereIn('id', $lineIds)->update(['ordered' => true]);
                }
                if ($data['status'] === PurchaseOrder::STATUS_RECEIVED) {
                    TakeoffLine::whereIn('id', $lineIds)->update(['ordered' => true, 'on_site' => true]);
                }
            }
        });

        return back()->with('success', "{$purchaseOrder->number} marked {$data['status']}.");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if (! $purchaseOrder->isDraft()) {
            return back()->with('error', 'Only draft purchase orders can be deleted.');
        }

        $purchaseOrder->delete();

        return back()->with('success', 'Draft purchase order deleted.');
    }

    public function pdf(PurchaseOrder $purchaseOrder): \Illuminate\Http\Response
    {
        $purchaseOrder->load(['project:id,name,address', 'vendor:id,name,location,phone,email', 'items']);

        return Pdf::loadView('pdf.purchase-order', ['po' => $purchaseOrder])
            ->setPaper('letter')
            ->download($purchaseOrder->number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')],
            'expected_delivery' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.takeoff_line_id' => ['nullable', 'integer', Rule::exists('takeoff_lines', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Normalize validated item payloads into row arrays; linked takeoff lines
     * must belong to the PO's project.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function itemRows(array $items, Project $project): array
    {
        $projectLineIds = $project->takeoffLines()->pluck('id')->all();

        $rows = [];
        $sort = 1;
        foreach ($items as $item) {
            $lineId = $item['takeoff_line_id'] ?? null;
            if ($lineId !== null && ! in_array((int) $lineId, $projectLineIds, true)) {
                abort(422, 'Takeoff line does not belong to this project.');
            }

            $qty = isset($item['qty']) ? (float) $item['qty'] : null;
            $unitPrice = $item['unit_price_cents'] ?? null;
            $total = ($qty !== null && $unitPrice !== null) ? (int) round($qty * $unitPrice) : null;

            $rows[] = [
                'takeoff_line_id' => $lineId,
                'description' => $item['description'],
                'qty' => $qty,
                'unit' => $item['unit'] ?? null,
                'unit_price_cents' => $unitPrice,
                'total_cents' => $total,
                'sort' => $sort++,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'number' => $po->number,
            'vendor_id' => $po->vendor_id,
            'vendor_name' => $po->vendor_name,
            'status' => $po->status,
            'total_cents' => $po->total_cents,
            'expected_delivery' => $po->expected_delivery?->toDateString(),
            'notes' => $po->notes,
            'sent_at' => $po->sent_at?->toDateString(),
            'received_at' => $po->received_at?->toDateString(),
            'created_at' => $po->created_at->toDateString(),
            'created_by' => $po->createdBy?->name,
            'allowed_transitions' => PurchaseOrder::TRANSITIONS[$po->status] ?? [],
            'items' => $po->items->map(fn ($i) => [
                'id' => $i->id,
                'takeoff_line_id' => $i->takeoff_line_id,
                'description' => $i->description,
                'qty' => $i->qty,
                'unit' => $i->unit,
                'unit_price_cents' => $i->unit_price_cents,
                'total_cents' => $i->total_cents,
            ])->values(),
        ];
    }
}

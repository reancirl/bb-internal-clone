<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $type = $request->string('type')->toString();
        $perPage = $this->perPage($request);

        $vendors = Vendor::query()
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            }))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'type', 'location', 'phone', 'email', 'url', 'notes'])
            ->withQueryString();

        return Inertia::render('vendors/index', [
            'vendors' => $vendors,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'per_page' => $perPage,
            ],
            'typeOptions' => Vendor::TYPES,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * Resolve the rows-per-page, restricted to the allowed options (default 10).
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 10);

        return in_array($perPage, [10, 20, 30], true) ? $perPage : 10;
    }

    public function store(Request $request): RedirectResponse
    {
        Vendor::create($this->validateData($request));

        return back()->with('success', 'Vendor added.');
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($this->validateData($request));

        return back()->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendor->delete();

        return back()->with('success', 'Vendor removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(Vendor::TYPES)],
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'url' => 'nullable|url|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
    }
}

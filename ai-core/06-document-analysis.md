## Diagram Requirement (Mandatory)

Diagrams MUST be included when describing:
- System architecture
- Application workflows
- Data flow
- Request/response pipelines
- State transitions
- Feature interaction flows
- Service communication
- Queue/event processing

Prefer using Mermaid syntax for all diagrams.

If Mermaid is not supported or becomes unreadable, use:
1. Structured ASCII diagrams
2. Step-based flow sections
3. Segmented sub-diagrams

---

# Diagram Standards

## Primary Format: Mermaid

Use clean and readable Mermaid diagrams.

Requirements:
- Keep diagrams vertically oriented when possible
- Avoid excessive crossing lines
- Use meaningful labels
- Keep node text concise
- Split overly large diagrams into multiple sections
- Prefer multiple focused diagrams over one massive diagram

Example:

```mermaid
flowchart TD
    A[Client Request]
    B[API Layer]
    C[Service Layer]
    D[(Database)]

    A --> B
    B --> C
    C --> D

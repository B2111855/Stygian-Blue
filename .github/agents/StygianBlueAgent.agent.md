---
description: 'Describe what this custom agent does and when to use it.'
tools: []
---

1. Database Awareness

Always read and understand the project SQL schema file before generating any query.

Use only tables, columns, constraints, and relationships that truly exist in the schema.

Validate every query to avoid invalid names or incorrect relationships.

Prefer existing views when they provide the needed data.

Handle NULL values, enum values, and foreign keys carefully.

2. SQL Quality

Produce clean, optimized, and safe SQL.

Avoid unnecessary subqueries and heavy operations.

Provide a short explanation of the logic behind joins and filters.

Do not expose sensitive or private user information.

Follow best practices for indexing whenever suggesting improvements.

3. UI Rules

Do NOT use icons in page titles.

Icons are allowed only in menus, sidebars, or action buttons.

Keep headers clean and text-only.

Follow a minimal, modern, responsive layout.

Prioritize clarity over decoration.

4. UX Rules

Ensure layouts are simple, consistent, and easy to navigate.

Maintain strong visual hierarchy (title → sections → content).

Use readable typography, proper spacing, and good contrast.

Recommend mobile-first design improvements when relevant.

Maintain accessible interactions and predictable behaviors.

5. Output Style

Explanations should be short, clear, and practical.

Always provide the final answer plus a brief justification.

Do not make assumptions not supported by the SQL schema or project requirements.

6. Language Rule

Always respond in Vietnamese, regardless of prompt language or topic, unless explicitly asked otherwise.
FEATURE: Agent detail page with configurable KPI targets

Managers configure KPI targets for an agent on the existing user edit page. 

Clicking the agent's name on the dashboard opens a new agent detail page showing today's actuals as 
progress bars against those targets, alongside last-30-days stats.

ACCEPTANCE

- KPI fields (Daily Call Target, Conversion Rate Target Percent) appear in the user add/edit page
- KPI fields appear ONLY when role is Agent
- Validation: integers >= 0 for call, 0-100 for conversion rate.
- New page at /agents/{agent} showing last-30-days stats and recent call logs in a table.
- Detail page shows today's actuals vs each target as a progress bar. No target set => hide that bar.
- Agent names on the dashboard link to the new detail page.

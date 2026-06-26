## ADDED Requirements

### Requirement: Follow-ups page access

The system SHALL provide a backoffice Follow-ups page, reachable from a sidebar entry, that is accessible only to authenticated users with the `admin` or `supervisor` role.

#### Scenario: Supervisor opens the Follow-ups page

- **WHEN** a user with the `supervisor` role navigates to the Follow-ups page
- **THEN** the page loads and displays the leads needing follow-up

#### Scenario: Admin opens the Follow-ups page

- **WHEN** a user with the `admin` role navigates to the Follow-ups page
- **THEN** the page loads and displays the leads needing follow-up

#### Scenario: Agent is denied access

- **WHEN** a user with only the `agent` role requests the Follow-ups page
- **THEN** the system denies access with an authorization error

#### Scenario: Sidebar entry is present

- **WHEN** a supervisor or admin views the backoffice sidebar
- **THEN** a "Follow-ups" navigation entry linking to the Follow-ups page is shown

### Requirement: Lead qualifies by missed or unanswered last call

A lead SHALL appear in the follow-ups list when the outcome of its latest call (the call with the most recent `called_at`) is `Missed` or `No Answer`.

#### Scenario: Latest call was missed

- **WHEN** a lead's most recent call has outcome `Missed`
- **THEN** the lead appears in the follow-ups list

#### Scenario: Latest call was unanswered

- **WHEN** a lead's most recent call has outcome `No Answer`
- **THEN** the lead appears in the follow-ups list

#### Scenario: Latest call had a non-qualifying outcome

- **WHEN** a lead's most recent call has outcome `Successful Contact`
- **AND** the lead has no callback reminder due on or before today
- **THEN** the lead does not appear in the follow-ups list

#### Scenario: An earlier call was missed but the latest was not

- **WHEN** a lead has an earlier call with outcome `Missed` and a later call with outcome `Interested`
- **AND** the lead has no callback reminder due on or before today
- **THEN** the lead does not appear in the follow-ups list

#### Scenario: Lead has no calls

- **WHEN** a lead has no call logs
- **AND** the lead has no callback reminder due on or before today
- **THEN** the lead does not appear in the follow-ups list

### Requirement: Lead qualifies by due callback reminder

A lead SHALL appear in the follow-ups list when it has a callback reminder (`type = 'callback'`) whose `remind_at` falls on or before the end of the current day, regardless of its latest call outcome.

#### Scenario: Callback reminder is due today

- **WHEN** a lead has a callback reminder with `remind_at` set to today
- **THEN** the lead appears in the follow-ups list

#### Scenario: Callback reminder is overdue

- **WHEN** a lead has a callback reminder with `remind_at` before today
- **THEN** the lead appears in the follow-ups list

#### Scenario: Callback reminder is in the future

- **WHEN** a lead's only callback reminder has `remind_at` after today
- **AND** the lead's latest call outcome is not `Missed` or `No Answer`
- **THEN** the lead does not appear in the follow-ups list

### Requirement: Each lead appears once

The follow-ups list SHALL include each qualifying lead exactly once, even when it qualifies under both the missed/unanswered-call rule and the due-callback-reminder rule.

#### Scenario: Lead qualifies under both rules

- **WHEN** a lead's latest call outcome is `Missed`
- **AND** the same lead also has a callback reminder due on or before today
- **THEN** the lead appears exactly once in the follow-ups list

### Requirement: Follow-up row detail

For each lead in the follow-ups list, the system SHALL display the lead name, phone number, assigned agent, the last call outcome and date, and the next reminder date.

#### Scenario: Row shows the required fields

- **WHEN** a qualifying lead is rendered in the follow-ups list
- **THEN** the row shows the lead's name, phone number, assigned agent name, the outcome and date of the latest call, and the date of the next reminder

#### Scenario: Lead has no assigned agent

- **WHEN** a qualifying lead has no assigned agent
- **THEN** the row renders without an agent name in place of crashing

#### Scenario: Lead qualifies only by call outcome with no reminder

- **WHEN** a qualifying lead has no upcoming reminder
- **THEN** the row renders the last call outcome and date and shows no next reminder date

### Requirement: Row links to call log detail

Each follow-up row SHALL be clickable and navigate to the call log detail page (`backoffice.call-logs.show`) for the lead's latest call. When the lead has no call logs, the row SHALL instead navigate to the lead detail page (`backoffice.leads.show`).

#### Scenario: Clicking a row with a latest call

- **WHEN** a user clicks a follow-up row for a lead whose latest call has id `42`
- **THEN** the system navigates to the call log detail page for call log `42`

#### Scenario: Clicking a row for a lead with no calls

- **WHEN** a user clicks a follow-up row for a lead that qualifies only by a due callback reminder and has no call logs
- **THEN** the system navigates to that lead's detail page

## ADDED Requirements

### Requirement: Follow-ups page offers queue tabs

The Follow-ups page SHALL present four queue tabs that filter the leads list: **All**, **Overdue**, **Due today**, and **Needs call**. Each tab SHALL restrict the displayed leads to the subset matching that queue's rule, drawn from the leads that already qualify for follow-up.

#### Scenario: All tabs are visible

- **WHEN** an agent opens the Follow-ups page
- **THEN** the page shows the **All**, **Overdue**, **Due today**, and **Needs call** queue tabs

#### Scenario: Selecting a queue filters the list

- **WHEN** an agent selects a queue tab other than **All**
- **THEN** the leads list shows only the leads matching that queue's rule

### Requirement: All queue is the default

The system SHALL treat **All** as the default queue. When no queue is selected, or the requested queue value is unrecognized, the page SHALL display the **All** queue. The sidebar link to the Follow-ups page SHALL resolve to the **All** queue.

#### Scenario: No queue specified

- **WHEN** an agent opens the Follow-ups page without specifying a queue
- **THEN** the **All** queue is active and shows every lead needing follow-up

#### Scenario: Unrecognized queue value

- **WHEN** the Follow-ups page is requested with a queue value that is not one of the four queues
- **THEN** the system falls back to the **All** queue

#### Scenario: Sidebar link uses the default

- **WHEN** an agent navigates to Follow-ups from the sidebar
- **THEN** the **All** queue is active

### Requirement: Overdue queue lists past-due reminders

The **Overdue** queue SHALL contain only leads whose earliest callback reminder is due before today.

#### Scenario: Reminder due before today

- **WHEN** a lead's earliest callback reminder is due on a date earlier than today
- **THEN** the lead appears in the **Overdue** queue

#### Scenario: Reminder due today is excluded

- **WHEN** a lead's earliest callback reminder is due today
- **THEN** the lead does not appear in the **Overdue** queue

### Requirement: Due today queue lists reminders due today

The **Due today** queue SHALL contain only leads whose earliest callback reminder is due today.

#### Scenario: Reminder due today

- **WHEN** a lead's earliest callback reminder is due today
- **THEN** the lead appears in the **Due today** queue

#### Scenario: Reminder due on another day is excluded

- **WHEN** a lead's earliest callback reminder is due before or after today
- **THEN** the lead does not appear in the **Due today** queue

### Requirement: Needs call queue lists bad last outcomes

The **Needs call** queue SHALL contain only leads whose latest call outcome is `Missed` or `No Answer`.

#### Scenario: Latest call was missed or unanswered

- **WHEN** a lead's latest call has an outcome of `Missed` or `No Answer`
- **THEN** the lead appears in the **Needs call** queue

#### Scenario: Latest call had another outcome

- **WHEN** a lead's latest call has an outcome other than `Missed` or `No Answer`
- **THEN** the lead does not appear in the **Needs call** queue

### Requirement: Each queue tab shows its lead count

Each queue tab SHALL display a count of the leads that fall in that queue. The counts SHALL reflect every queue regardless of which queue is currently active, and SHALL count the full matching set independent of pagination.

#### Scenario: Counts shown for every tab

- **WHEN** an agent opens the Follow-ups page on any queue
- **THEN** each of the four tabs shows the number of leads in that queue

#### Scenario: Count reflects the full set, not the current page

- **WHEN** a queue contains more leads than fit on one page
- **THEN** that tab's count reflects the total number of matching leads, not just the leads on the current page

#### Scenario: Empty queue shows zero

- **WHEN** no leads match a queue's rule
- **THEN** that tab shows a count of 0

### Requirement: Queue selection preserves sorting and pagination

Filtering by queue SHALL preserve the page's existing sort order and pagination. Pagination links SHALL retain the active queue so navigating between pages keeps the selected queue.

#### Scenario: Pagination keeps the active queue

- **WHEN** an agent on a non-default queue navigates to another page of results
- **THEN** the same queue remains active and only that queue's leads are shown

#### Scenario: Sort order is unchanged by queue

- **WHEN** an agent switches between queues
- **THEN** leads within each queue follow the same sort order as the unfiltered list

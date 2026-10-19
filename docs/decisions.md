# Decisions

Choices made on purpose, with the reason. Status: **decided** (in the code), **open** (needs the owner), **not built yet**.

## Model

### D1. Aggregates: Member and Title (decided)
- **Member** owns its **Loans** and **Fines**. Rules enforced here: max 5 loans, card not expired, outstanding fines not above €5, fine on late return.
- **Title** owns its **Copies** and the **Reservation** queue. Rules enforced here: a copy is lent to one person only, first come first served, no reservation while a copy is on the shelf.
- Aggregates refer to each other **by id** (`titleId`, `copyId`, `memberId`), never by object. Member methods take ids and a due period, not `Title` or `Copy`.
- Use cases that touch both (borrow, return) load and save both aggregates in one transaction.

### D2. Reservations belong to Title, not Member (decided)
- A reservation connects a member and a title, so both are natural owners. The deciding question was which rule must be strictly consistent.
- The **queue and the hold** ("this copy is for the first in line, nobody else gets it") is the hard rule. It is the same kind of problem as lending one copy twice, and it needs the title's copies. So Title owns the queue.
- The **limit of 3 reservations per member** spans all titles and is a soft rule. It is checked in `ReservationService` through the `ReservationQuery` port (`countForMember`).
- Consequence: two simultaneous requests from one member can both pass the count and end with a 4th reservation. Accepted; it harms nobody.
- Member keeps no reservation list.

### D3. Identity is generated in the domain (decided)
- Ids are UUID v7 strings created when an entity is created (`Uuid::generate()`), not assigned by the database.
- Why: a new Loan or Fine must be referable by other new objects before anything is saved, and tests need no database.
- Repositories pass the stored id to the constructor when loading. Database columns hold the string, no auto-increment.

### D4. Money is an integer number of cents (decided)
- No floats. All amounts in code and policy are `…InCents`. Currency is euro, implied.
- A `Money` value object is not needed by any rule yet, so it is not added.

### D5. Policy numbers live in `LibraryPolicy` (decided, to revisit)
- All limits, fees and durations are constants in one class, so changing "5 loans" or "28 days" is a one-line edit.
- Limit: they are global. Different limits for staff members will need a policy object chosen per member type.

### D6. Time is a dependency (decided)
- Domain and use cases take a PSR-20 `ClockInterface`; tests use `FixedClock`. Nothing calls `new DateTimeImmutable()` for "now".
- The clock is injected  into the service constructors.

### D7. Exceptions per process (decided)
- `BorrowingException`, `ReturningException`, `RenewalException`, `ReservationException`, `FineException` live in `Shared/Domain`, because the aggregates throw them.
- "Not found" (member, title) currently reuses the process exception. Acceptable for now.

## Rule interpretations

| # | Question | Decision | Status |
|---|---|---|---|
| R1 | Is a loan overdue on the due date or the day after? | The day after. Due date = borrowed date + loan period; on the due date it is not overdue. | decided |
| R2 | Is exactly €5.00 owed "more than €5"? | No. €5.00 can still borrow, €5.01 cannot. | decided |
| R3 | Is the fine cap per loan or per member? | Per loan (€10), as the brief says. A member can owe more in total. | decided |
| R4 | Does the new-release rule look at the Title or the Copy? | The Title: `addedDate` of the title. Adding a copy does not make a title new. | decided |
| R5 | When is a title a "new release"? | Added less than 6 whole months before the loan. Added exactly 6 months ago = standard 28 days. | decided |
| R6 | Does returning early or on time cost anything? | No fine. Overdue days are counted up to the return date, not up to "now". | decided |
| R7 | Returning a copy that is not on loan | Error (`ReturningException`), also when it was already returned. | decided |
| R8 | A reservation for a title with a copy on the shelf | Refused (`ReservationException`). | decided |
| R9 | A member without reservation when copies > waiting reservations | May borrow. Only copies "covered" by the queue are kept for the reservers. | decided |

## Open questions for the owner

Each has a default that the code currently follows.

1. **Card expiry day.** The card is expired as soon as the expiry date has passed *at midnight*, so on the expiry date itself it is already expired. Should it still be valid for the whole expiry day? (Probably yes.)
2. **Reserving a title the member has on loan right now.** Currently allowed. Sensible?
3. **Who may reserve.** Currently any member within the limit of 3. Should a member with an expired card, or one who owes more than €5, be refused?
4. **Held copy and expiring card.** A copy is held for a member for 3 days. If the card expires or fines pass €5 in that time, does the copy still wait for them?
5. **Renewal on the due date.** Allowed on the due date, or only before it?
6. **Lost or damaged copy.** Out of scope for now. Needs its own process (fine? write-off of the copy?).
7. **Staff members.** Different limits, details unknown. Will need a policy object (see D5).
8. **Holidays.** Due dates may not fall on a closed day. Not built.

## Known gaps / not built yet

- **3-day hold:** a returned copy stays reserved for the queue without an expiry.
- **Payments and write-off:** `Member::pay` exists but is not tied to individual fines; fines older than 2 years are not written off. Member keeps both a list of fines and a running balance.
- **Overdue notices:** no domain events yet. A loan does not "become" overdue by itself, so this needs a scheduled use case that finds overdue loans and emits `LoanBecameOverdue`.
- **Copy availability** is stored on `Copy` and also follows from active Loans. They are kept in sync by the use cases (see D1). This is the place where the system could become inconsistent.
- **Loan history** grows inside Member. Loading only active loans may be needed later.

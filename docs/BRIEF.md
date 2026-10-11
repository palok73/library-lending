# Town Library Lending System: project brief and working guide

Part 1 is the brief as a product owner would hand it over. Part 2 is a guide on how to tackle it as a DDD learning project (not from the owner).

---

# PART 1: BRIEF

**From:** Marta, head librarian, Riverside Town Library
**To:** the developer

## Background
We're a small library with about 8,000 books and 1,200 members. Today we track loans in a spreadsheet, and it's a mess. People borrow more than they should, nobody notices overdue books until months later, and we sometimes lend the same copy to two people on the same day. We want software that knows our rules and doesn't let us break them by accident.

For now we only need the *rules and the logic*. Screens and a database come later, and a different team will do those. Your part should work on its own, so we can plug it into whatever we choose.

## Words we use (please use them in the code too)
- **Title** is a book as a work ("Animal Farm"). **Copy** is a physical item with a barcode. We have several copies of popular titles.
- **Member** is someone with a library card.
- **Loan** is a copy lent to a member until a due date.
- **Reservation** is a member's place in the queue for a title.
- **Fine** is money a member owes for returning late.

## What we need

### 1. Borrowing
*As a librarian, I want to lend a copy to a member, so that they can take it home.*
- A member can have at most **5 loans** at the same time.
- A copy can only be lent to one person at a time.
- A member who owes **more than €5 in fines** can't borrow anything until they pay.
- A member whose card has **expired** can't borrow.
- The standard loan is **28 days**. New releases (less than 6 months in our collection) are **14 days**. This considers new Title - not a new copy of a title.

### 2. Returning
*As a librarian, I want to record a returned copy, so that it's available again.*
- Returning a copy ends the loan. If it's late, the member gets a fine of **€0.20 per day**, to a maximum of **€10 per loan**.
- Returning something that isn't on loan should be an error. This has happened. Don't ask.

### 3. Renewing
*As a member, I want to extend my loan, so that I can finish the book.*
- A loan can be renewed **twice**, each time for the standard period from the day of renewal.
- A loan can't be renewed if the member has overdue loans, or if somebody has **reserved** the title.
- New releases can't be renewed.

### 4. Reservations
*As a member, I want to reserve a title that's out, so that I get it when it comes back.*
- A reservation is for a title, not a copy. First come, first served.
- When a copy comes back and there's a waiting reservation, that copy is **held for that member for 3 days**. After that, it goes to the next in line, or back on the shelf.
- A member can have at most **3 reservations**.
- If a copy is available on the shelf, there's no reason to reserve.

### 5. Fines
*As a librarian, I want to see and settle what a member owes.*
- Fines can be paid in full or in part.
- Fines older than **2 years** are written off (nobody will ever pay them, honestly).

### 6. Overdue notices
*As a librarian, I want to know who has overdue books, so that I can remind them.*
- When a loan becomes overdue, the system should **announce it** so that something else (email, a printed list) can react. We haven't decided how people get notified yet.

## Not now
Payments by card, book search, new member registration, statistics, and the web or mobile interface.

## Things we're not sure about
- Staff members probably get different limits. We'll tell you when we know.
- We might want to close the library on holidays, so that due dates don't fall on a closed day. Maybe later.
- We might change the numbers (5 loans, €5, 28 days) next year. Please don't make that painful.

## What "done" means for us
We'll give you a list of real situations from last month, and the software should say the same as a careful librarian would.

---

# PART 2: HOW TO TACKLE IT (DDD learning guide)

## Constraints for the exercise
- Plain PHP + PHPUnit. No framework, no database, no HTTP.
- Persistence is a **port** (interface) with an **in-memory** implementation for tests.
- Time is a dependency: a `Clock` interface, so "overdue" can be tested.
- Mentor loop: you decide and write; reviewer asks "why" and challenges.

## Step 0: Setup (about 15 minutes)
`composer init` as a library, PSR-4 for `src/` and `tests/`, PHPUnit as a dev dependency, `declare(strict_types=1)` everywhere. Commit after each green test.

## Step 1: Ubiquitous language (before any code)
Write `docs/glossary.md` in the owner's words:
- Define Title, Copy, Member, Loan, Reservation, Fine in one sentence each.
- Note which words are *different things* (Title vs Copy), and which look like one thing but may be two.
- Add a "rules" section: every number from the brief (5 loans, €5, 28/14 days, €0.20/day, €10 cap, 2 renewals, 3 reservations, 3-day hold, 2 years).
- Keep a separate `docs/decisions.md` for choices you made on purpose.

## Step 2: Questions for the owner
The brief has holes on purpose. Write your questions and decide on a sensible default for each. Some prompts:
- Is a loan "overdue" on the due date or the day after?
- Is exactly €5.00 owed "more than €5"?
- What happens to a held copy if the member's card expires while it waits?
- Can a loan be renewed on the due date, or only before it?
- Is the fine cap per loan or per member?
- What if a copy is returned damaged or lost? (Out of scope? Say so.)
- Are the new-release rule and the 14 days based on the date the copy joined the collection, or the title?

## Step 3: Rules into test sentences
Turn each rule into a test name in the owner's language, for example "a member with five loans cannot borrow a sixth", "a copy cannot be lent twice". Write these names down first; then tests, then code. If you can't phrase a rule as a sentence, you have found a question for step 2.

## Step 4: Find the model (the actual DDD work)
Questions to answer, and argue about, before writing classes:
1. Which things have **identity** (entities), and which are just **values**? (Money, DueDate, Barcode, LoanPeriod, CardExpiry candidates.)
2. Which rules need to be **consistent in one transaction**? That tells you the aggregate boundaries. Look at:
   - "max 5 loans per member"
   - "a copy is lent to only one person"
   - "queue of reservations for a title"
   - "fines owed by a member"
3. Does a Loan belong inside Member, inside Copy, or stand alone? What would each choice make hard?
4. Where does a rule that spans two aggregates live? (Domain service, or the application layer?) Try to keep aggregates small, and reference others by ID, not by object.
5. How do you represent Money safely (no floats; amount in cents plus currency)?
6. Which numbers are policy that may change (limits, fees, durations)? Where do they live so changing them isn't painful?

## Step 5: Build in this order (each step is red, green, commit)
1. **Value objects:** Money, a DateRange or DueDate, Barcode/IDs. Immutable and self-validating.
2. **Loan lifecycle:** create a loan, compute due date (standard vs new release), return it, compute a fine (rate, cap, rounding).
3. **Member rules:** limit of loans, fines threshold, card expiry. Decide how these rules get evaluated, and what a refusal looks like (a domain exception per reason, with names from the glossary).
4. **Renewal rules.**
5. **Reservations:** queue per title, hold on return, the 3-day expiry, the limit of 3.
6. **Fines:** accumulating, partial payments, write-off after 2 years.
7. **Domain events:** `LoanBecameOverdue` (and others you find). Decide how they are collected and published without the domain knowing who listens.
8. **Ports:** repositories (`MemberRepository`, `LoanRepository`, ...) as interfaces in the domain, with in-memory implementations in `tests/` or `Infrastructure/`.
9. **Application layer:** use cases (`BorrowCopy`, `ReturnCopy`, `RenewLoan`, `ReserveTitle`, ...). They load aggregates, call domain behaviour, save, and dispatch events. No business rules here.
10. **Clock:** a `Clock` interface with a fixed-time test implementation.

## Step 6: Review checklist
- Does any domain class import infrastructure or a framework? (It must not.)
- Can any object be created in an invalid state? Can you make illegal states unrepresentable?
- Are rules in the aggregates, or leaking into use cases?
- Do class and method names match the glossary, word for word?
- Are the limits and fees configurable in one place?
- Are the tests readable as business sentences? Could the owner read their names?
- Is anything shared between aggregates by object reference where an ID would do?

## Traps to expect (and discuss)
- **Anemic model:** classes with only getters and setters, with all rules in services. Ask where each rule lives.
- **Aggregate too big:** one `Library` object holding everything. Ask what really has to be consistent together.
- **Aggregate too small:** a rule that needs two aggregates to be consistent, with no owner.
- **Time:** `new DateTimeImmutable()` inside domain code makes tests flaky. Use the Clock.
- **Floats for money.**
- **Over-engineering:** do not add events, factories or value objects that no rule needs yet. Add them when a test or rule asks for one.

## Stretch goals (after the core works)
- Holiday calendar: due dates never fall on a closed day.
- Different limits for staff members (a policy object per member type).
- An event listener that produces a printed "overdue list", without touching the domain.
- A small CLI or script in `app/` wiring the objects, like the `app/index.php` smoke script in the distance project.

## How to work with a reviewer
Send, in this order: (1) glossary, (2) list of questions with your defaults, (3) the rule-sentences (test names), (4) your proposed aggregates with reasons, (5) code, one slice at a time. The design questions in step 4 are worth most of the time.

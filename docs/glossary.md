# Library lending 

## Purpose
project for small town library, to track book lendings, returning, overdues and to title copies (to not lend more copies than they have)

## Domain Terms
### Title
Particular Book title to lend/borrow. Every Title is identified by Author, Title, year of publication.
Every Title has its language. Title can be identified by ISBN number
### Copy 
Library has multiply copies of same Title. So one Title has multiply copies which can be lended.
Copy is identified by internal library number
### Member
Member is someone with a library card. Member has his loans, reservations, fines
### Loan
Loan is a copy lent to a member until a due date.
### Reservation
is a member's place in the queue for a title. A reservation is for a **Title**, not for a particular **Copy**.
A member can make a reservation only when **no Copy** of the **Title** is available on the shelf (when a copy is available, he just borrows it).
The queue belongs to the **Title**; reservations are served first come, first served.
### Fine
is money a member owes for returning late. Fine is given to member for certain overdue Loan.

## Nouns (Processes)
### 1. Borrowing
*As a librarian, I want to lend a copy to a member, so that they can take it home.*
- A member can have at most **5 loans** at the same time.
- A copy can only be lent to one person at a time.
- A member who owes **more than €5 in fines** can't borrow anything until they pay.
- A member whose card has **expired** can't borrow.
- The standard loan is **28 days**. New releases (less than 6 months in our collection) are **14 days**. This considers the **Title** (when it joined the collection), not a new copy of a title.

### 2. Returning
*As a librarian, I want to record a returned copy, so that it's available again.*
- Returning a copy ends the loan. If it's late, the member gets a fine of **€0.20 per day**, to a maximum of **€10 per
 loan**.
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

## Rules

All numbers are in `LibraryPolicy`. Amounts are in cents in code.

| Rule | Value |
|---|---|
| Max. active loans per member | 5 |
| Max. outstanding fines to borrow | €5.00 (more than €5.00 blocks borrowing) |
| Standard loan period | 28 days |
| New release loan period | 14 days |
| New release = title in collection | less than 6 months |
| Fine per day late | €0.20 |
| Max. fine per loan | €10.00 |
| Renewals per loan | 2 (each for 28 days from the day of renewal) |
| Max. reservations per member | 3 |
| Hold of a returned copy for a reserver | 3 days |
| Fine is written off after | 2 years |

Other rules:
- A copy is lent to one member at a time.
- A member with an expired card can't borrow.
- Returning a copy that is not on loan is an error.
- A loan is overdue from the day after its due date.
- Renewal is refused if the member has overdue loans, if the title is reserved, or if it is a new release.
- Reservations are not possible while a copy of the title is on the shelf.

See `docs/decisions.md` for interpretations, open questions and what is not built yet.

# Vehicle expense entry

Select a purchased vehicle. Its purchase date is displayed automatically and cannot be changed from the expense form.

Each row contains **Party name → Bill no. → Date of bill → Item name → Line total (INR)**. Different rows may use different parties. Search the row's party list or use **+** to create a party and select it immediately without refreshing or losing the entry.

The bill date must be strictly later than the vehicle purchase date and within the selected financial year. A bill dated on or before the purchase date shows an inline warning and cannot be saved. This rule is validated by both JavaScript and PHP. Changing the vehicle refreshes the purchase date and revalidates all bill dates.

Each row saves one bill with one item and quantity 1. The entered line total is the full amount; it is not multiplied by quantity. Bill numbers remain unique across every vehicle in the selected company. All row parties and dates are checked before insertion; an invalid batch is not partially saved.

Existing stored bills remain unchanged, and old API clients may still provide a top-level party_id with their bill rows or legacy item list. New UI requests supply party_id on each bill row. No database migration or reinstall is required.

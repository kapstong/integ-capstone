# Financial Process Fix To-Do

- [x] Lock down GL manual entry (API + UI) to make GL read-only
- [x] Fix invoice tax payable account (use Sales Tax Payable 2108)
- [x] Fix disbursement JE polarity and use numeric COA codes
- [x] Enforce Collection linkage to AR (payments_received requires invoice + customer)
- [x] Enforce AP linkage to disbursements (payments_made requires bill + vendor)
- [x] Post bill expenses using bill items and valid COA accounts
- [x] Add budget checks + actual updates for bills and non-AP disbursements
- [x] Define adjustment JE rules + accounts (credit memo, debit memo, write-off, discount)
- [x] Update AP UI to remove/replace vendor “collections” flows now blocked by API
- [x] Review remaining integration postings for COA account_id vs account_code mismatches

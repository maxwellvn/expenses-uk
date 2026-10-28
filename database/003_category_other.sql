-- Lets claimants say what an "Other" expense was.
USE uk_expenses;
ALTER TABLE claim_items ADD COLUMN category_other VARCHAR(80) NULL AFTER category;

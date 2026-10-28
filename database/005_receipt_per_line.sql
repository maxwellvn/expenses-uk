-- Receipts belong to the expense line they prove. NULL = older claims where receipts were attached to the whole claim.
USE uk_expenses;
ALTER TABLE claim_files ADD COLUMN item_position SMALLINT UNSIGNED NULL AFTER claim_id;

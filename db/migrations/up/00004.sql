-- Transactional outbox: one row per created ledger transaction, written inside
-- the same database transaction as the ledger row, so an event is recorded if
-- and only if the transaction committed. Rows are delivered to a message broker
-- (or any consumer) by OutboxService::dispatch() with at-least-once semantics.
-- No immutability trigger here: the dispatcher updates status/attempts.

CREATE TABLE `outbox` (
  `outboxid` int(11) NOT NULL AUTO_INCREMENT,
  `transactionid` int(11) NOT NULL,
  `uuid` binary(16) NOT NULL,
  `event` varchar(40) COLLATE utf8_bin NOT NULL,
  `status` enum('pending','processed') COLLATE utf8_bin NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT 0,
  `lasterror` varchar(500) COLLATE utf8_bin DEFAULT NULL,
  `createdat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processedat` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`outboxid`),
  KEY `idx_outbox_status` (`status`, `outboxid`),
  KEY `idx_outbox_transactionid` (`transactionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- No foreign key to transaction(transactionid) on purpose: extended entities may
-- store their ledger in a different table (e.g. transaction_extended), and the
-- same-transaction write plus the ledger immutability already guarantee the
-- referenced row exists and never changes.

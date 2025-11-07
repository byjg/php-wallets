alter table account
    add last_uuid binary(16) null;

alter table statement
    add uuid binary(16) null,
    algorithm = instant;

create unique index idx_statement_uuid on statement (uuid);

<?php

namespace App\Domains\Ticketing\DTOs;

final readonly class IssueTicketData
{
    public function __construct(public int $assignmentId, public int $revenueHeadId, public string $confirmation, public string $requestKey) {}

    public static function fromArray(array $data): self
    {
        return new self((int) $data['assignment_id'], (int) $data['revenue_head_id'], $data['confirmation'], $data['request_key']);
    }
}

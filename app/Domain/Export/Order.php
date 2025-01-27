<?php
/**
 * lel since 01.11.18
 */

namespace App\Domain\Export;

class Order implements OrderDTO
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    public function getOrderTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->data['orderDateTime']);
    }

    public function getOrderNumber(): string
    {
        return $this->data['orderNumber'];
    }

    public function getLineItems(): array
    {
        return array_map(fn (array $v): OrderLineItem => new OrderLineItem($v), $this->data['lineItems'] ?? []);
    }
}

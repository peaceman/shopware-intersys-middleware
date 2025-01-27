<?php

namespace App\Domain\Export;

interface OrderDTO
{
    public function getId(): string;

    public function getOrderTime(): \DateTimeImmutable;

    public function getOrderNumber(): string;

    /**
     * @return OrderLineItemDTO[]
     */
    public function getLineItems(): array;
}

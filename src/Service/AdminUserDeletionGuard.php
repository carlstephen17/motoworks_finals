<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CustomerOrderRepository;
use App\Repository\MotorcycleRepository;
use App\Repository\OrderItemRepository;
use App\Repository\PromoClaimRepository;
use App\Repository\ServiceBookingRepository;
use App\Repository\ServiceRecordRepository;

final readonly class AdminUserDeletionGuard
{
    public function __construct(
        private OrderItemRepository $orders,
        private CustomerOrderRepository $customerOrders,
        private ServiceBookingRepository $bookings,
        private PromoClaimRepository $claims,
        private MotorcycleRepository $motorcycles,
        private ServiceRecordRepository $serviceRecords,
    ) {
    }

    public function hasLinkedRecords(User $user): bool
    {
        return (bool) (
            $this->orders->count(['user' => $user])
            || $this->customerOrders->count(['user' => $user])
            || $this->bookings->count(['user' => $user])
            || $this->bookings->count(['createdByStaff' => $user])
            || $this->claims->count(['user' => $user])
            || $this->claims->count(['createdByStaff' => $user])
            || $this->motorcycles->count(['user' => $user])
            || $this->serviceRecords->count(['customer' => $user])
            || $this->serviceRecords->count(['createdByStaff' => $user])
        );
    }
}
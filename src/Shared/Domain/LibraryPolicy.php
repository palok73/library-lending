<?php
declare(strict_types=1);

namespace LibraryLanding\Shared\Domain;

class LibraryPolicy {
    public const int NEW_RELEASE_MAX_AGE_IN_MONTHS = 6;
    public const int MAX_LOAN_PER_MEMBER = 5;
    public const int DUE_DAYS_STANDARD = 28;
    public const int DUE_DAYS_NEW_RELEASE = 14;  
    public const int MAX_NEGATIVE_BALANCE_IN_CENTS = 500;

    public const int DAILY_FINE_RATE_IN_CENTS=20;

    public const int MAX_FINE_AMOUNT_IN_CENTS=1000;

    public const int RESERVATION_HOLD_PERIOD_IN_DAYS=3;

    public const int WRITE_OFF_PERIOD_IN_DAYS=730; // 2 years

    public const int MAX_RENEW_COUNT=2;

    public const int MAX_RESERVATIONS_PER_MEMBER = 3;
}

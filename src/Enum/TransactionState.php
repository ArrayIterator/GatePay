<?php
declare(strict_types=1);

namespace GatePay\Core\Enum;

enum TransactionState: string
{
    /**
     * State constants representing the different stages of the transaction processing lifecycle.
     */
    case PENDING = 'PENDING';

    /**
     * State constant representing the beginning phase of the transaction processing.
     */
    case BEGIN = 'BEGIN';

    /**
     * State constant representing the then phase of the transaction processing.
     */
    case SUCCESS = 'SUCCESS';

    /**
     * State constant representing the catch phase of the transaction processing.
     */
    case ERROR = 'ERROR';

    /**
     * State constant representing the finally phase of the transaction processing.
     */
    case FINAL = 'FINAL';
}

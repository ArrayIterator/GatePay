<?php
declare(strict_types=1);

namespace GatePay\Core\Enum;

enum GatewayAction: string
{
    // --- 1. CORE TRANSACTIONS ---
    /**
     * Direct deduction of funds from the customer's account (Sale).
     * INIT → SUCCESS / FAILED
     */
    case CHARGE = 'CHARGE';

    /**
     * Processing a payment that involves customer redirection to the gateway's
     * hosted page (e.g., PayPal, Midtrans Snap).
     * INIT → PENDING → SUCCESS / FAILED
     */
    case PURCHASE = 'PURCHASE';

    /**
     * Reserving/holding funds on the account without actual deduction.
     * INIT → SUCCESS / FAILED → STATUS: AUTHORIZED
     */
    case AUTHORIZE = 'AUTHORIZE';

    /**
     * Capturing funds that were previously authorized.
     * INIT → SUCCESS / FAILED → STORING: CAPTURED
     */
    case CAPTURE = 'CAPTURE';

    /**
     * Cancelling an authorized transaction that has not been captured yet.
     * INIT → SUCCESS / FAILED → STATUS: VOIDED / CANCEL TRANSACTION
     */
    case VOID = 'VOID';

    /**
     * Returning funds to the customer.
     * INIT → SUCCESS / FAILED
     */
    case REFUND = 'REFUND';

    /**
     * Cancelling a transaction that is still in a pending state.
     * INIT → SUCCESS / FAILED → STATUS: CANCELED
     */
    case CANCEL = 'CANCEL';

    /**
     * Verifying the validity of a payment method without processing a charge.
     * INIT → SUCCESS / FAILED
     */
    case VERIFY = 'VERIFY';

    /**
     * Forcing a transaction to expire (e.g., Virtual Accounts or time-limited links).
     * INIT → SUCCESS / FAILED → STATUS: EXPIRED
     */
    case EXPIRE = 'EXPIRE';

    // --- 2. DIRECT API & PUSH PAYMENTS ---
    /**
     * Direct charge processing without customer redirection (Server-to-Server).
     * INIT → SUCCESS / FAILED
     */
    case DIRECT_CHARGE = 'DIRECT_CHARGE';

    /**
     * Direct refund processing without manual approval workflows.
     */
    case DIRECT_REFUND = 'DIRECT_REFUND';

    /**
     * Handling payment disputes or chargebacks raised by customers. (Chargeback management)
     */
    case DISPUTE = 'DISPUTE';

    /**
     * Submitting evidence or responding to a dispute raised by the customer.
     */
    case CHALLENGE_DISPUTE = 'CHALLENGE_DISPUTE';

    /**
     * Accepting the dispute and refunding the customer.
     */
    case RESOLVE_DISPUTE = 'RESOLVE_DISPUTE';

    /**
     * Retrieving details and status of a specific dispute.
     */
    case GET_DISPUTE = 'GET_DISPUTE';

    /**
     * Retrieving a list of disputes for a specific charge or customer.
     */
    case GET_DISPUTES = 'GET_DISPUTES';

    // --- 4. BALANCE, PAYOUTS & RECONCILIATION ---
    /**
     * Retrieving the detailed status and payload of a specific charge.
     */
    case GET_CHARGE = 'GET_CHARGE';

    /**
     * Retrieving general transaction history or logs.
     */
    case GET_TRANSACTION = 'GET_TRANSACTION';

    /**
     * Checking the account balance within the payment gateway (e.g., Stripe, PayPal).
     */
    case GET_BALANCE = 'GET_BALANCE';

    /**
     * Retrieving historical balance changes, including payouts and fees.
     */
    case GET_BALANCE_HISTORY = 'GET_BALANCE_HISTORY';

    /**
     * Retrieving financial statements or payouts for reconciliation.
     */
    case GET_STATEMENT = 'GET_STATEMENT';

    /**
     * Processing Webhooks or Instant Payment Notifications (IPN) sent by the gateway.
     */
    case HANDLE_NOTIFICATION = 'HANDLE_NOTIFICATION';

    // --- 4. CARD & PAYMENT METHOD MANAGEMENT (VAULTING) ---
    /**
     * Saving card data securely and returning a reusable token (Tokenization).
     */
    case REGISTER_CARD = 'REGISTER_CARD';

    /**
     * Retrieving saved card token details.
     */
    case GET_CARD = 'GET_CARD';

    /**
     * Deleting a saved card token.
     */
    case DELETE_CARD = 'DELETE_CARD';

    /**
     * Saving non-card payment methods for future use.
     */
    case CREATE_PAYMENT_METHOD = 'CREATE_PAYMENT_METHOD';

    /**
     * Retrieving a saved payment method.
     */
    case GET_PAYMENT_METHOD = 'GET_PAYMENT_METHOD';

    /**
     * Updating details of a saved payment method.
     */
    case UPDATE_PAYMENT_METHOD = 'UPDATE_PAYMENT_METHOD';

    /**
     * Deleting a saved payment method.
     */
    case DELETE_PAYMENT_METHOD = 'DELETE_PAYMENT_METHOD';

    // --- 5. SUBSCRIPTIONS ---
    /**
     * Creating a recurring billing profile.
     */
    case CREATE_SUBSCRIPTION = 'CREATE_SUBSCRIPTION';

    /**
     * Retrieving subscription profile details.
     */
    case GET_SUBSCRIPTION = 'GET_SUBSCRIPTION';

    /**
     * Modifying an existing subscription (e.g., upgrading/downgrading plans).
     */
    case UPDATE_SUBSCRIPTION = 'UPDATE_SUBSCRIPTION';

    /**
     * Cancelling a recurring billing profile.
     */
    case CANCEL_SUBSCRIPTION = 'CANCEL_SUBSCRIPTION';

    /**
     * Temporarily suspending a subscription.
     */
    case PAUSE_SUBSCRIPTION = 'PAUSE_SUBSCRIPTION';

    /**
     * Reactivating a paused subscription.
     */
    case RESUME_SUBSCRIPTION = 'RESUME_SUBSCRIPTION';

    // --- 6. CUSTOMERS ---
    /**
     * Creating a customer profile in the gateway vault.
     */
    case CREATE_CUSTOMER = 'CREATE_CUSTOMER';

    /**
     * Retrieving customer profile details.
     */
    case GET_CUSTOMER = 'GET_CUSTOMER';

    /**
     * Updating a customer profile.
     */
    case UPDATE_CUSTOMER = 'UPDATE_CUSTOMER';

    /**
     * Deleting a customer profile.
     */
    case DELETE_CUSTOMER = 'DELETE_CUSTOMER';

    // --- 7. INVOICE & BILLING ---
    /**
     * Creating a new invoice.
     */
    case CREATE_INVOICE = 'CREATE_INVOICE';

    /**
     * Retrieving invoice details.
     */
    case GET_INVOICE = 'GET_INVOICE';

    /**
     * Updating an invoice.
     */
    case UPDATE_INVOICE = 'UPDATE_INVOICE';

    /**
     * Deleting an invoice.
     */
    case DELETE_INVOICE = 'DELETE_INVOICE';

    /**
     * Dispatching an invoice to the customer via email or API.
     */
    case SEND_INVOICE = 'SEND_INVOICE';

    // --- 8. REDIRECT-BASED GATEWAYS OR AUTHENTICATION BASED TOKEN ---
    /**
     * Retrieving token or session details for initiating a redirect-based payment flow (e.g., PayPal etc,).
     */
    case GET_TOKEN = 'GET_TOKEN';

    /**
     * Refreshing or updating an existing token for continued frontend interactions.
     * OR update existing token with new parameters (e.g., amount, customer details)=
     */
    case UPDATE_TOKEN = 'UPDATE_TOKEN';

    /**
    * Invalidating or expiring a token to prevent further use.
    */
    case EXPIRE_TOKEN = 'EXPIRE_TOKEN';

    /**
     * Deleting a token from the gateway's system.
     */
    case DELETE_TOKEN = 'DELETE_TOKEN';

    /**
     * Retrieving a payment link or URL for redirect-based payment flows.
     * INIT → ISSUED → PENDING → SUCCESS / EXPIRED
     */
    case GET_PAYMENT_LINK = 'GET_PAYMENT_LINK';

    /**
     * Creating a new payment link or URL for redirect-based payment flows.
     * INIT → ISSUED → PENDING → SUCCESS / EXPIRED
     */
    case CREATE_PAYMENT_LINK = 'CREATE_PAYMENT_LINK';

    /**
     * Custom Action
     */
    case CUSTOM = 'CUSTOM';

    /**
     * Test Action
     */
    case TEST = 'TEST';
}

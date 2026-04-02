<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Enum;

enum GatewayAction: string
{
    // --- 1. CORE TRANSACTIONS ---
    /**
     * Direct deduction of funds from the customer's account (Sale).
     */
    case CHARGE = 'charge';

    /**
     * Processing a payment that involves customer redirection to the gateway's
     * hosted page (e.g., PayPal, Midtrans Snap).
     */
    case PURCHASE = 'purchase';

    /**
     * Reserving/holding funds on the account without actual deduction.
     */
    case AUTHORIZE = 'authorize';

    /**
     * Capturing funds that were previously authorized.
     */
    case CAPTURE = 'capture';

    /**
     * Cancelling an authorized transaction that has not been captured yet.
     */
    case VOID = 'void';

    /**
     * Returning funds to the customer.
     */
    case REFUND = 'refund';

    /**
     * Cancelling a transaction that is still in a pending state.
     */
    case CANCEL = 'cancel';

    /**
     * Verifying the validity of a payment method without processing a charge.
     */
    case VERIFY = 'verify';

    /**
     * Forcing a transaction to expire (e.g., Virtual Accounts or time-limited links).
     */
    case EXPIRE = 'expire';

    // --- 2. DIRECT API & PUSH PAYMENTS ---
    /**
     * Direct charge processing without customer redirection (Server-to-Server).
     */
    case DIRECT_CHARGE = 'direct_charge';

    /**
     * Direct refund processing without manual approval workflows.
     */
    case DIRECT_REFUND = 'direct_refund';

    /**
     * Handling payment disputes or chargebacks raised by customers. (Chargeback management)
     */
    case DISPUTE = 'dispute';

    /**
     * Submitting evidence or responding to a dispute raised by the customer.
     */
    case CHALLENGE_DISPUTE = 'challenge_dispute';

    /**
     * Accepting the dispute and refunding the customer.
     */
    case RESOLVE_DISPUTE = 'resolve_dispute';

    /**
     * Retrieving details and status of a specific dispute.
     */
    case GET_DISPUTE = 'get_dispute';

    /**
     * Retrieving a list of disputes for a specific charge or customer.
     */
    case GET_DISPUTES = 'get_disputes';

    // --- 4. BALANCE, PAYOUTS & RECONCILIATION ---
    /**
     * Retrieving the detailed status and payload of a specific charge.
     */
    case GET_CHARGE = 'get_charge';

    /**
     * Retrieving general transaction history or logs.
     */
    case GET_TRANSACTION = 'get_transaction';

    /**
     * Checking the account balance within the payment gateway (e.g., Stripe, PayPal).
     */
    case GET_BALANCE = 'get_balance';

    /**
     * Retrieving historical balance changes, including payouts and fees.
     */
    case GET_BALANCE_HISTORY = 'get_balance_history';

    /**
     * Retrieving financial statements or payouts for reconciliation.
     */
    case GET_STATEMENT = 'get_statement';

    /**
     * Processing Webhooks or Instant Payment Notifications (IPN) sent by the gateway.
     */
    case HANDLE_NOTIFICATION = 'handle_notification';

    // --- 4. CARD & PAYMENT METHOD MANAGEMENT (VAULTING) ---
    /**
     * Saving card data securely and returning a reusable token (Tokenization).
     */
    case REGISTER_CARD = 'register_card';

    /**
     * Retrieving saved card token details.
     */
    case GET_CARD = 'get_card';

    /**
     * Deleting a saved card token.
     */
    case DELETE_CARD = 'delete_card';

    /**
     * Saving non-card payment methods for future use.
     */
    case CREATE_PAYMENT_METHOD = 'create_payment_method';

    /**
     * Retrieving a saved payment method.
     */
    case GET_PAYMENT_METHOD = 'get_payment_method';

    /**
     * Updating details of a saved payment method.
     */
    case UPDATE_PAYMENT_METHOD = 'update_payment_method';

    /**
     * Deleting a saved payment method.
     */
    case DELETE_PAYMENT_METHOD = 'delete_payment_method';

    // --- 5. SUBSCRIPTIONS ---
    /**
     * Creating a recurring billing profile.
     */
    case CREATE_SUBSCRIPTION = 'create_subscription';

    /**
     * Retrieving subscription profile details.
     */
    case GET_SUBSCRIPTION = 'get_subscription';

    /**
     * Modifying an existing subscription (e.g., upgrading/downgrading plans).
     */
    case UPDATE_SUBSCRIPTION = 'update_subscription';

    /**
     * Cancelling a recurring billing profile.
     */
    case CANCEL_SUBSCRIPTION = 'cancel_subscription';

    /**
     * Temporarily suspending a subscription.
     */
    case PAUSE_SUBSCRIPTION = 'pause_subscription';

    /**
     * Reactivating a paused subscription.
     */
    case RESUME_SUBSCRIPTION = 'resume_subscription';

    // --- 6. CUSTOMERS ---
    /**
     * Creating a customer profile in the gateway vault.
     */
    case CREATE_CUSTOMER = 'create_customer';

    /**
     * Retrieving customer profile details.
     */
    case GET_CUSTOMER = 'get_customer';

    /**
     * Updating a customer profile.
     */
    case UPDATE_CUSTOMER = 'update_customer';

    /**
     * Deleting a customer profile.
     */
    case DELETE_CUSTOMER = 'delete_customer';

    // --- 7. INVOICE & BILLING ---
    /**
     * Creating a new invoice.
     */
    case CREATE_INVOICE = 'create_invoice';

    /**
     * Retrieving invoice details.
     */
    case GET_INVOICE = 'get_invoice';

    /**
     * Updating an invoice.
     */
    case UPDATE_INVOICE = 'update_invoice';

    /**
     * Deleting an invoice.
     */
    case DELETE_INVOICE = 'delete_invoice';

    /**
     * Dispatching an invoice to the customer via email or API.
     */
    case SEND_INVOICE = 'send_invoice';

    // --- 8. REDIRECT-BASED GATEWAYS OR AUTHENTICATION BASED TOKEN ---
    /**
     * Retrieving token or session details for initiating a redirect-based payment flow (e.g., PayPal etc,).
     */
    case GET_TOKEN = 'get_token';

    /**
     * Refreshing or updating an existing token for continued frontend interactions.
     * OR update existing token with new parameters (e.g., amount, customer details)=
     */
    case UPDATE_TOKEN = 'update_token';

    /**
    * Invalidating or expiring a token to prevent further use.
    */
    case EXPIRE_TOKEN = 'expire_token';

    /**
     * Deleting a token from the gateway's system.
     */
    case DELETE_TOKEN = 'delete_token';

    /**
     * Retrieving a payment link or URL for redirect-based payment flows.
     */
    case GET_PAYMENT_LINK = 'get_payment_link';

    /**
     * Creating a new payment link or URL for redirect-based payment flows.
     */
    case CREATE_PAYMENT_LINK = 'create_payment_link';

    /**
     * Custom Action
     */
    case CUSTOM = 'custom';
}

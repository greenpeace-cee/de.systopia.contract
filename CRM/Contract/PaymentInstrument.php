<?php

interface CRM_Contract_PaymentInstrument {

    /**
     * Create a new payment instance
     *
     * @param array $params - Parameters depend on the implementation
     *
     * @return CRM_Contract_PaymentInstrument|null
     */
    public static function create ($params);

    /**
     * Get the display name of the payment instrument
     *
     * @return string
     */
    public static function displayName();

    /**
     * Get payment specific form fields
     *
     * @return array
     */
    public static function formFields ();

    /**
     * Get payment specific JS variables for contract forms
     *
     * @param CRM_Contract_PaymentInstrument|null -
     *      Returned form variables might depend on the values of an instance
     *
     * @return array
     */
    public static function formVars ($instance = null);

    /**
     * Get available cycle days
     *
     * @return array
     */
    public static function getCycleDays ();

    /**
     * Get payment parameters
     *
     * @return array
     */
    public function getParameters ();

    /**
     * Check if a given payment instrument ID refers to an instance of the class
     *
     * @param string $payment_instrument_id
     *
     * @return boolean
     */
    public static function isInstance ($payment_instrument_id);

    /**
     * Load payment data for an instance linked to a recurring contribution with the given ID
     *
     * @param string $recurring_contribution_id
     *
     * @return CRM_Contract_PaymentInstrument|null
     */
    public static function loadByRecurringContributionId ($recurring_contribution_id);

    /**
     * Map submitted form values to API paramters
     *
     * @param array $submitted
     *
     * @return array
     */
    public static function mapSubmittedValues ($submitted);

    /**
     * Calculate the next possible cycle day
     *
     * @return int
     */
    public static function nextCycleDay ();

    /**
     * Pause payment
     *
     * @return void
     */
    public function pause ();

    /**
     * Resume paused payment
     *
     * @return void
     */
    public function resume ();

    /**
     * Set payment parameters
     *
     * @param array $params - Parameters depend on the implementation
     *
     * @return void
     */
    public function setParameters ($params);

    /**
     * Terminate payment
     *
     * @param string $reason - Reason for the termination
     *
     * @return void
     */
    public function terminate ($reason);

}

?>

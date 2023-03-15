<?php

interface CRM_Contract_PaymentAdapter {

    /**
     * Get metadata about the payment adapter
     *
     * @return array
     *  [display_name] => (string) - Name that will be displayed in forms
     *  [id]           => (string) - ID used to indentify the kind of payment
     */
    public static function adapterInfo ();

    /**
     * Create a new payment
     *
     * @param array $params - Parameters depend on the implementation
     *
     * @throws Exception
     *
     * @return array $recurring_contribution_id
     */
    public static function create ($params);

    /**
     * Create a new payment by merging an existing payment and an update,
     * The existing payment will be terminated.
     *
     * @param int $recurring_contribution_id
     * @param string $current_adapter
     * @param array $update
     * @param int $activity_type_id
     *
     * @throws Exception
     *
     * @return int - ID of the newly created recurring contribution
     */
    public static function createFromUpdate ($recurring_contribution_id, $current_adapter, $update, $activity_type_id = null);

    /**
     * Get a list of possible cycle days
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - list of cycle days as integers
     */
    public static function cycleDays ($params = []);

    /**
     * Get payment specific form field specifications
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - List of form field specifications
     */
    public static function formFields ($params = []);

    /**
     * Get payment specific JS variables for forms
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - Form variables
     */
    public static function formVars ($params = []);

    /**
     * Determine if a recurring contribution is an instance of this payment method
     *
     * @param int $recurring_contribution_id
     *
     * @return bool
     */
    public static function isInstance ($recurring_contribution_id);

    /**
     * Map submitted form values to paramters for a specific API call
     *
     * @param string $apiEndpoint
     * @param array $submitted
     *
     * @throws Exception
     *
     * @return array - API parameters
     */
    public static function mapSubmittedFormValues ($apiEndpoint, $submitted);

    /**
     * Get the next contribution date for a contract
     * 
     * @param array $params - Necessary parameters, depending on implementation
     * @param string $today - Optional parameter to simulate a different current time
     *
     * @return string - Next contribution date (YYYY-MM-DD)
     **/
    // public static function nextContributionDate ($params = [], $today = 'now');

    /**
     * Pause payment
     *
     * @param int $recurring_contribution_id
     *
     * @throws Exception
     *
     * @return void
     */
    public static function pause ($recurring_contribution_id);

    /**
     * Resume paused payment
     *
     * @param int $recurring_contribution_id
     * @param array $update
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function resume ($recurring_contribution_id, $update = []);

    /**
     * Revive a cancelled payment
     *
     * @param int $recurring_contribution_id
     * @param array $update
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function revive ($recurring_contribution_id, $update = []);

    /**
     * Calculate the start date for a recurring contribution
     * 
     * @param array $params
     * @param string $today
     * 
     * @return string - Calculated start date
     */
    public static function startDate($params = [], $today = "now");

    /**
     * Terminate payment
     *
     * @param int $recurring_contribution_id
     * @param string $reason
     *
     * @throws Exception
     *
     * @return void
     */
    public static function terminate ($recurring_contribution_id, $reason = "CHNG");

    /**
     * Update payment data
     *
     * @param int $recurring_contribution_id
     * @param array $params - Parameters depend on the implementation
     * @param int $activity_type_id
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params, $activity_type_id = null);

}

?>

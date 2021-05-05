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
     * @return array $payment_data -
     *      [recurring_contribution_id] => (string) ID of the associated recurring contribution
     */
    public static function create ($params);

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
     * @return array - List of form field specifications
     */
    public static function formFields ();

    /**
     * Get necessary JS files for forms
     *
     * @return array - Paths to the files
     */
    public static function formScripts ();

    /**
     * Get necessary templates for forms
     *
     * @return array - Paths to the templates
     */
    public static function formTemplates ();

    /**
     * Get payment specific JS variables for forms
     *
     * @return array - Form variables
     */
    public static function formVars ();

    /**
     * Check if a recurring contribution is associated with the implemented
     * payment method
     *
     * @param int $recurring_contribution_id
     *
     * @throws Exception
     *
     * @return boolean
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
     * Map update parameters to payment adapter parameters
     *
     * @param array $update_params
     *
     * @return array - Payment adapter parameters
     */
    public static function mapUpdateParameters ($update_params);

    /**
     * Get the next possible cycle day
     *
     * @return int - the next cycle day
     */
    public static function nextCycleDay ();

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
     *
     * @throws Exception
     *
     * @return void
     */
    public static function resume ($recurring_contribution_id);

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
    public static function terminate ($recurring_contribution_id, $reason);

    /**
     * Update payment data
     *
     * @param int $recurring_contribution_id
     * @param array $params - Parameters depend on the implementation
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params);

}

?>

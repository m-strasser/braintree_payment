<?php

namespace Drupal\braintree_payment;

use \Braintree\ClientToken;
use \Braintree\Configuration;
use \Drupal\payment_forms\CreditCardForm as _CreditCardForm;

/**
 * @file
 * Defines the Credit Card Form on the clientside.
 */
class CreditCardForm extends _CreditCardForm {

  const JS_SDK_VERSION = '3.50.0';

  static protected $issuers = [];
  static protected $cvcLabel = [];

  /**
   * Defines the form that shall be rendered.
   */
  public function form(array $form, array &$form_state, \Payment $payment) {
    $method = $payment->method;
    $form = parent::form($form, $form_state, $payment);
    unset($form['issuer']);

    $form['braintree-payment-nonce'] = array(
      '#type' => 'hidden',
      '#default_value' => '',
    );

    // Add token & public key to settings for clientside.
    $settings['braintree_payment']['pmid_' . $method->pmid] = array(
      'payment_token' => $method->controller->getClientToken($method),
      'pmid' => $method->pmid,
    );

    // Attach client side JS files and settings to javascript settings variable.
    $form['#attached']['js'][] = [
      'type' => 'setting',
      'data' => $settings,
    ];
    $form['#attached']['js'] += static::scriptAttachments();

    $data = $method->controller_data['billing_data'];
    $default = ['keys' => [], 'required' => FALSE, 'display' => 'hidden'];
    $context = $payment->contextObj;
    $bd = static::extraDataFields();
    foreach ($bd as $name => &$field) {
      $config = isset($data[$name]) ? $data[$name] + $default : $default;
      $field['#controller_required'] = $config['required'];
      $field['#default_value'] = '';
      if ($context) {
        foreach ($config['keys'] as $key) {
          if ($value = $context->value($key)) {
            $field['#default_value'] = $value;
            break;
          }
        }
      }
      if (!$this->shouldDisplay($field, $config['display'])) {
        $field['#type'] = 'hidden';
        $field['#value'] = $field['#default_value'];
        $field['#attributes']['data-braintree-name'] = $field['#braintree_field'];
      }
    }

    $form['billing_data'] = $bd + [
      '#type' => 'container',
    ];
    return $form;
  }

  /**
   * Javascripts needed for the payment form.
   *
   * @return array
   *   #attached-array with all the needed scripts.
   */
  protected static function scriptAttachments() {
    $base_url = 'https://js.braintreegateway.com/web';
    $version = static::JS_SDK_VERSION;
    $js["$base_url/$version/js/client.min.js"] = [
      'type' => 'external',
      'group' => JS_LIBRARY,
    ];
    $js["$base_url/$version/js/hosted-fields.min.js"] = [
      'type' => 'external',
      'group' => JS_LIBRARY,
    ];
    $js[drupal_get_path('module', 'braintree_payment') . '/braintree.js'] = [
      'type' => 'file',
      'group' => JS_DEFAULT,
    ];
    return $js;
  }

  /**
   * Validation function, real validation on the clientside.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    // Braintree takes care of the real validation, client-side.
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $payment->method_data['braintree-payment-nonce'] = $values['braintree-payment-nonce'];

    $bd = [];
    foreach ($element['billing_data'] as $field) {
      if (!empty($field['#controller_required']) && empty($field['#value'])) {
        form_error($field, t('!name field is required.', array('!name' => $field['#title'])));
      }
      // Only pass non-empty fields in method data.
      if (!empty($field['#value'])) {
        $bd[$field['#braintree_field']] = $field['#value'];
      }
    }
    // Always provide at least an empty address.
    $payment->method_data['billing_data'] = $bd + [
      'company' => '',
      'countryCodeAlpha2' => '',
      'extendedAddress' => '',
      'firstName' => '',
      'lastName' => '',
      'locality' => '',
      'postalCode' => '',
      'region' => '',
      'streetAddress' => '',
    ];
  }

  /**
   * Defines additional data fields.
   *
   * @return array
   *   A form-API style array defining fields that map to the braintree billing
   *   data using the #braintree_field attribute.
   */
  public static function extraDataFields() {
    require_once DRUPAL_ROOT . '/includes/locale.inc';
    $fields['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First name'),
      '#braintree_field' => 'firstName',
    ];

    $fields['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last name'),
      '#braintree_field' => 'lastName',
    ];

    $fields['company'] = [
      '#type' => 'textfield',
      '#title' => t('Company'),
      '#braintree_field' => 'company',
    ];

    $fields['street_address'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 1'),
      '#braintree_field' => 'streetAddress',
    ];

    $fields['address_line2'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 2'),
      '#braintree_field' => 'extendedAddress',
    ];

    $fields['country'] = [
      '#type' => 'select',
      '#options' => country_get_list(),
      '#title' => t('Country'),
      '#braintree_field' => 'countryCodeAlpha2',
    ];

    $fields['postcode'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#braintree_field' => 'postalCode',
    ];

    $fields['city'] = [
      '#type' => 'textfield',
      '#title' => t('City/Locality'),
      '#braintree_field' => 'locality',
    ];

    $fields['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region/State'),
      '#braintree_field' => 'region',
    ];

    return $fields;
  }

  /**
   * Check whether a specific field should be displayed.
   */
  protected function shouldDisplay($field, $display) {
    return ($display == 'always') || (empty($field['#default_value']) && $display == 'ifnotset');
  }

}

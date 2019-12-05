<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsTerminal class.
 */
class CommerceNetsTerminal {

  public $AutoAuth;
  public $PaymentMethodList;
  public $Language;
  public $OrderDescription;
  public $RedirectOnError;
  public $RedirectUrl;

  /**
   * Class constructor.
   */
  public function __construct(
    $auto_auth,
    $payment_method_list,
    $language,
    $order_description,
    $redirect_on_error,
    $redirect_url
  ) {
    $this->AutoAuth = $auto_auth;
    $this->PaymentMethodList = $payment_method_list;
    $this->Language = $language;
    $this->OrderDescription = $order_description;
    $this->RedirectOnError = $redirect_on_error;
    $this->RedirectUrl = $redirect_url;
  }

}

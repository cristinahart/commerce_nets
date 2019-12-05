<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsOrder class.
 */
class CommerceNetsOrder {

  public $Amount;
  public $CurrencyCode;
  public $Force3DSecure;
  public $Goods;
  public $OrderNumber;
  public $UpdateStoredPaymentInfo;

  /**
   * Class constructor.
   */
  public function __construct(
    $amount,
    $currency_code,
    $force_3d_secure,
    $goods,
    $order_number,
    $update_stored_payment_info
  ) {
    $this->Amount = $amount;
    $this->CurrencyCode = $currency_code;
    $this->Force3DSecure = $force_3d_secure;
    $this->Goods = $goods;
    $this->OrderNumber = $order_number;
    $this->UpdateStoredPaymentInfo = $update_stored_payment_info;
  }

}

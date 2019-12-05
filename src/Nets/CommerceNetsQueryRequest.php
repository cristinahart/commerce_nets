<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsQueryRequest class.
 */
class CommerceNetsQueryRequest {

  public $TransactionId;

  /**
   * Class constructor.
   */
  public function __construct($transaction_id) {
    $this->TransactionId = $transaction_id;
  }

}

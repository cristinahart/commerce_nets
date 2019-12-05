<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsProcessRequest class.
 */
class CommerceNetsProcessRequest {

  public $Description;
  public $Operation;
  public $TransactionAmount;
  public $TransactionId;
  public $TransactionReconRef;

  /**
   * Class constructor.
   */
  public function __construct(
    $description,
    $operation,
    $transaction_amount,
    $transaction_id,
    $transaction_recon_ref
  ) {
    $this->Description = $description;
    $this->Operation = $operation;
    $this->TransactionAmount = $transaction_amount;
    $this->TransactionId = $transaction_id;
    $this->TransactionReconRef = $transaction_recon_ref;
  }

}

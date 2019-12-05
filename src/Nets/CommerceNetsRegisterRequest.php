<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsRegisterRequest class.
 */
class CommerceNetsRegisterRequest {

  public $AvtaleGiro;
  public $CardInfo;
  public $Customer;
  public $Description;
  public $DnBNorDirectPayment;
  public $Environment;
  public $MicroPayment;
  public $Order;
  public $Recurring;
  public $ServiceType;
  public $Terminal;
  public $TransactionId;
  public $TransactionReconRef;

  /**
   * Class constructor.
   */
  public function __construct(
    $avtale_giro,
    $card_info,
    $customer,
    $description,
    $dnb_nor_direct_payment,
    $environment,
    $micro_payment,
    $order,
    $recurring,
    $service_type,
    $terminal,
    $transaction_id,
    $transaction_recon_ref
  ) {
    $this->AvtaleGiro = $avtale_giro;
    $this->CardInfo = $card_info;
    $this->Customer = $customer;
    $this->Description = $description;
    $this->DnBNorDirectPayment = $dnb_nor_direct_payment;
    $this->Environment = $environment;
    $this->MicroPayment = $micro_payment;
    $this->Order = $order;
    $this->Recurring = $recurring;
    $this->ServiceType = $service_type;
    $this->Terminal = $terminal;
    $this->TransactionId = $transaction_id;
    $this->TransactionReconRef = $transaction_recon_ref;
  }

}

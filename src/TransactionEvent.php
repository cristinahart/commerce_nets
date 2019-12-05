<?php

namespace Drupal\commerce_nets;

use Drupal\commerce_nets\Nets\CommerceNetsProcessRequest;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Symfony\Component\EventDispatcher\Event;

class TransactionEvent extends Event {

  const EVENT_NAME = 'event.commerce_nets.transaction';

  /**
   * @var \Drupal\commerce_payment\Entity\PaymentInterface
   */
  protected $payment;

  /**
   * @var string
   */
  protected $action;

  /**
   * @var \Drupal\commerce_nets\Nets\CommerceNetsProcessRequest
   */
  protected $netsProcessRequest;

  public function __construct(PaymentInterface $payment, $action, CommerceNetsProcessRequest $nets_process_request) {
    $this->payment = $payment;
    $this->action = $action;
    $this->netsProcessRequest = $nets_process_request;
  }

  public function getPayment() {
    return $this->payment;
  }

  public function getAction() {
    return $this->action;
  }

  public function myEventDescription() {
    return "Alter a nets transaction";
  }
}

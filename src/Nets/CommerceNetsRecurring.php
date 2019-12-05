<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsRecurring class.
 */
class CommerceNetsRecurring {

  public $ExpiryDate;
  public $Frequency;
  public $Type;
  public $PanHash;

  /**
   * Class constructor.
   */
  public function __construct(
    $expiry_date,
    $frequency,
    $type,
    $pan_hash
  ) {
    $this->ExpiryDate       = $expiry_date;
    $this->Frequency        = $frequency;
    $this->Type             = $type;
    $this->PanHash          = $pan_hash;
  }

}

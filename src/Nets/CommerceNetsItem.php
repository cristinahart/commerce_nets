<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsItem class.
 */
class CommerceNetsItem {

  public $Amount;
  public $ArticleNumber;
  public $Discount;
  public $Handling;
  public $IsVatIncluded;
  public $Quantity;
  public $Shipping;
  public $Title;
  public $VAT;

  /**
   * Class constructor.
   */
  public function __construct(
    $amount,
    $article_number,
    $discount,
    $handling,
    $is_vat_included,
    $quantity,
    $shipping,
    $title,
    $vat
  ) {
    $this->Amount = $amount;
    $this->ArticleNumber = $article_number;
    $this->Discount = $discount;
    $this->Handling = $handling;
    $this->IsVatIncluded = $is_vat_included;
    $this->Quantity = $quantity;
    $this->Shipping = $shipping;
    $this->Title = $title;
    $this->VAT = $vat;
  }

}

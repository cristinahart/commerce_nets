<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsCustomer class.
 */
class CommerceNetsCustomer {

  public $Address1;
  public $Address2;
  public $CompanyName;
  public $CompanyRegistrationNumber;
  public $Country;
  public $CustomerNumber;
  public $Email;
  public $FirstName;
  public $LastName;
  public $PhoneNumber;
  public $Postcode;
  public $SocialSecurityNumber;
  public $Town;

  /**
   * Class constructor.
   */
  public function __construct(
    $address1,
    $address2,
    $company_name,
    $company_registration_number,
    $country,
    $customer_number,
    $email,
    $first_name,
    $last_name,
    $phone_number,
    $postcode,
    $social_security_number,
    $town
  ) {
    $this->Address1 = $address1;
    $this->Address2 = $address2;
    $this->CompanyName = $company_name;
    $this->CompanyRegistrationNumber = $company_registration_number;
    $this->Country = $country;
    $this->CustomerNumber = $customer_number;
    $this->Email = $email;
    $this->FirstName = $first_name;
    $this->LastName = $last_name;
    $this->PhoneNumber = $phone_number;
    $this->Postcode = $postcode;
    $this->SocialSecurityNumber = $social_security_number;
    $this->Town = $town;
  }

}

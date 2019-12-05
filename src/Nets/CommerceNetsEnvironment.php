<?php

namespace Drupal\commerce_nets\Nets;

/**
 * CommerceNetsEnvironment class.
 */
class CommerceNetsEnvironment {

  public $Language;
  public $OS;
  public $WebServicePlatform;

  /**
   * Class constructor.
   */
  public function __construct(
    $language,
    $os,
    $web_service_platform
  ) {
    $this->Language = $language;
    $this->OS = $os;
    $this->WebServicePlatform = $web_service_platform;
  }

}

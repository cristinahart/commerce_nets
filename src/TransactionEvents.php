<?php

namespace Drupal\commerce_nets;

use Symfony\Component\EventDispatcher\Event;

final class TransactionEvents extends Event {

  const PROCESS = 'event.commerce_nets.process';
}

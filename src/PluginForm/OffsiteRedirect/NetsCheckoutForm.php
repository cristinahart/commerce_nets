<?php

namespace Drupal\commerce_nets\PluginForm\OffsiteRedirect;

use Drupal\commerce_nets\NetsManager;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NetsCheckoutForm.
 *
 * Handles the initiation of Nets payments.
 */
class NetsCheckoutForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  /**
   * The Nets service.
   *
   * @var \Drupal\commerce_nets\NetsManager
   */
  protected $nets;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Private temp store.
   *
   * Acts like Session storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * Class constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(NetsManager $netsManager, LoggerInterface $logger, PrivateTempStore $privateTempStore) {
    $this->nets = $netsManager;
    $this->logger = $logger;
    $this->tempStore = $privateTempStore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_nets.manager'),
      $container->get('commerce_nets.logger'),
      $container->get('commerce_nets.tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\Payment $payment */
    // When dumping here, we have a new entity, use that by default.
    $payment = $this->entity;

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $payment_gateway_settings = $payment_gateway_plugin->getConfiguration();

    $use_redirect = TRUE;

    $urls = [
      'return' => $form['#return_url'] . '?capture=' . (int) $form['#capture'],
      'cancel' => $form['#cancel_url'],
    ];

    try {
      // Create a local transaction for the register event.
      $transaction_id = $this->nets->registerTransaction(
        $payment,
        $payment->getAmount(),
        $urls
      );

    }
    catch (\Exception $e) {
      $this->logger->error('Could not register new transaction for order @order_id.', array('@order_id' => $payment->getOrderId()));
      // If $use_redirect is set we are allow to throw an exception.
      if ($use_redirect) {
        throw new PaymentGatewayException('Could not register transaction. Please try again.');
      }
      return $this->buildReturnUrl($payment->getOrderId());
    }

    // Set transaction ID in session.
    $this->tempStore->set('transaction_id', $transaction_id);

    $this->logger->notice('Sending user to Nets. Order: @order_id.', array('@order_id' => $payment->getOrderId()));
    // Build the redirect URL.
    $terminal_url = $this->nets->terminalUrl($payment_gateway_settings['mode']);
    $merchant_id = $payment_gateway_settings['merchantid'];
    $options = [
      'merchantid' => $merchant_id,
      'transactionId' => $transaction_id,
    ];
    // Builds the redirect form.
    return $this->buildRedirectForm($form, $form_state, $terminal_url, $options, self::REDIRECT_GET);
  }

  /**
   * Builds the URL to the "return" page.
   *
   * @return \Drupal\Core\Url
   *   The "return" page URL.
   */
  protected function buildReturnUrl($order_id) {
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order_id,
      'step' => 'payment',
    ]);
  }

}

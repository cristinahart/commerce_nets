<?php

namespace Drupal\commerce_nets\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_nets\NetsManager;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsVoidsInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_price\Price;

/**
 * Provides the Nets payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "nets_checkout",
 *   label = "Nets Checkout",
 *   display_label = "Nets Checkout",
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_nets\PluginForm\OffsiteRedirect\NetsCheckoutForm",
 *   },
 * )
 */
class NetsCheckout extends OffsitePaymentGatewayBase implements SupportsVoidsInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * Service used for making API calls using Nets Checkout library.
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
   * Session storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * NetsCheckout constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\commerce_nets\NetsManager $netsManager
   *   The Nets manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, NetsManager $netsManager, LoggerInterface $logger, PrivateTempStore $tempStore) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    $this->nets = $netsManager;
    $this->logger = $logger;
    $this->tempStore = $tempStore;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_nets.manager'),
      $container->get('commerce_nets.logger'),
      $container->get('commerce_nets.tempstore'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'merchantid' => '',
      'netstoken' => '',
      'language' => 'en_GB',
      'order_description' => '',
      'transaction_id' => '',
      'use_redirect' => FALSE,
      'recurring' => FALSE,
      'override_list_builder' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['merchantid'] = [
      '#type' => 'textfield',
      '#title' => t('Merchant ID'),
      '#description' => t('Merchant ID issued by Nets.'),
      '#default_value' => $this->configuration['merchantid'],
      '#required' => TRUE,
    ];
    $form['netstoken'] = [
      '#type' => 'textfield',
      '#title' => t('Token'),
      '#description' => t('Nets token issued by Nets.'),
      '#default_value' => $this->configuration['netstoken'],
      '#required' => TRUE,
    ];
    $form['language'] = [
      '#type' => 'select',
      '#title' => t('Terminal language'),
      '#description' => t('Language to present the user at the Nets Terminal.'),
      '#options' => $this->nets->getAvailableLanguages(),
      '#default_value' => $this->configuration['language'],
    ];
    $form['order_description'] = [
      '#type' => 'textfield',
      '#title' => t('Order description'),
      '#description'  => t('Custom description to show on the Nets terminal. Can contain HTML.'),
      '#default_value' => $this->configuration['order_description'],
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => t('Advanced'),
    ];
    $form['advanced']['transaction_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Transaction ID'),
      '#description' => t('Custom Transaction ID to use. Note: Must be unique and can NOT be more than 32 characters long after evaluation. Leave empty for autogenerated ID at Nets.'),
      '#default_value' => $this->configuration['transaction_id'],
    );
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['token'] = array(
        '#theme' => 'token_tree_link',
        '#title' => t('Replacement patterns'),
        '#token_types' => array('commerce_order'),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['merchantid'] = $values['merchantid'];
      $this->configuration['netstoken'] = $values['netstoken'];
      $this->configuration['language'] = $values['language'];
      $this->configuration['order_description'] = $values['order_description'];
      $this->configuration['use_redirect'] = FALSE;
      $this->configuration['recurring'] = FALSE;
      $this->configuration['transaction_id'] = $values['advanced']['transaction_id'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {

    $remote_id = $request->get('transactionId');
    $response_code = $request->get('responseCode');
    $capture = $request->get('capture');

    // @todo: Check transaction ID mismatch.
    if ($this->tempStore->get('transaction_id') !== $remote_id) {
      throw new PaymentGatewayException(new FormattableMarkup('Mismatch between transaction ID in user session %session and transaction ID returned by NETS %nets.', ['%session' => $this->tempStore->get('transaction_id'), '%nets' => $remote_id]));
    }

    $message_variables = array(
      '%oid' => $order->id(),
      '%tid' => $remote_id,
      '%rc'  => $response_code,
    );

    if (empty($remote_id) || empty($response_code)) {
      throw new PaymentGatewayException(new FormattableMarkup('Return from Nets has wrong values for order: %oid, transactionId: %tid and responseCode: %rc.', $message_variables));
    }

    // We cannot rely on data we receive from NETS as there is no authorization,
    // checksum or hash. We will retrieve transaction status from NETS API.
    $payment_settings = $this->configuration;
    $nets_transaction = $this->nets->queryTransaction($payment_settings, $remote_id);

    if (isset($nets_transaction->ErrorLog)
      && isset($nets_transaction->ErrorLog->PaymentError)
      && isset($nets_transaction->ErrorLog->PaymentError->ResponseText)) {

      $message_variables['%reason'] = $nets_transaction->ErrorLog->PaymentError->ResponseText;
      $this->logger->error('There was a problem with payment for order %oid, reason: %reason', $message_variables);
      throw new PaymentGatewayException('Error at payment gateway.');
    }

    $action = $capture === '1' ? 'SALE' : 'AUTH';

    /** @var PaymentInterface $payment */
    $payment = $this->entityTypeManager->getStorage('commerce_payment')->create([
      'state' => 'new',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->entityId,
      'order_id' => $order->id(),
      'remote_state' => $response_code,
      'remote_id' => $remote_id,
      // We set this one so payment which hasn't been save can read a balance.
      'refunded_amount' => new Price(0, $order->getTotalPrice()->getCurrencyCode()),
    ]);
    try {
      $this->nets->processTransaction($payment, $action);
    }
    catch (\Exception $e) {
      $this->tempStore->delete('transaction_id');
      throw new PaymentGatewayException("Authorization failed.");
    }
    $payment->setState($action === 'SALE' ? 'completed' : 'authorization');
    $payment->setAuthorizedTime($this->time->getCurrentTime());
    $payment->save();

    // Delete transaction if from the session now that we have it in entity.
    $this->tempStore->delete('transaction_id');
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);

    try {
      // Void the payment.
      $this->nets->processTransaction($payment, 'ANNUL');
    }
    catch (\Exception $e) {
      throw new SoftDeclineException(t('Unable to void payment. Message: @message', ['@message' => $e->getMessage()]));
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);

    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertCaptureAmount($payment, $amount);

    if ($amount->lessThan($payment->getAmount())) {
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $parent_payment */
      $parent_payment = $payment;
      $payment = $parent_payment->createDuplicate();
    }

    try {
      $this->nets->processTransaction($payment, 'CAPTURE', $this->toMinorUnits($amount));
    }
    catch (\Exception $e) {
      throw new SoftDeclineException(t('Unable to capture payment. Message @message', array('@message' => $e->getMessage())));
    }

    // Set transaction status and amount to the one captured.
    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();

    // Update parent payment if one exists.
    if (isset($parent_payment)) {
      $parent_payment->setAmount($parent_payment->getAmount()->subtract($amount));
      if ($parent_payment->getAmount()->isZero()) {
        $parent_payment->setState('authorization_voided');
      }
      $parent_payment->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);

    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    try {
      $this->nets->processTransaction($payment, 'CREDIT', $this->toMinorUnits($amount));
    }
    catch (\Exception  $e) {
      throw new SoftDeclineException(t('Unable to credit payment. Message: @message',
        ['@message' => $e->getMessage()]));
    }

    // Set the state.
    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }
    $payment->setRefundedAmount($new_refunded_amount);

    $payment->save();
  }

  /**
   * Asserts that the refund amount is valid.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param \Drupal\commerce_price\Price $capture_amount
   *   The amount to be captured.
   *
   * @throws \Drupal\commerce_payment\Exception\InvalidRequestException
   *   Thrown when the capture amount is larger than the payment amount.
   */
  protected function assertCaptureAmount(PaymentInterface $payment, Price $capture_amount) {
    $amount = $payment->getAmount();
    if ($capture_amount->greaterThan($amount)) {
      throw new InvalidRequestException(sprintf("Can't capture more than %s.", $amount->__toString()));
    }
  }

}

<?php

namespace Drupal\commerce_nets;

use Drupal\commerce_nets\Nets\CommerceNetsCustomer;
use Drupal\commerce_nets\Nets\CommerceNetsEnvironment;
use Drupal\commerce_nets\Nets\CommerceNetsOrder;
use Drupal\commerce_nets\Nets\CommerceNetsProcessRequest;
use Drupal\commerce_nets\Nets\CommerceNetsQueryRequest;
use Drupal\commerce_nets\Nets\CommerceNetsRegisterRequest;
use Drupal\commerce_nets\Nets\CommerceNetsTerminal;
use Drupal\commerce_nets\Nets\AvtaleGiro;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;

/**
 * Class NetsService.
 */
class NetsManager implements NetsManagerInterface {

  const WDSL_TEST = 'https://test.epayment.nets.eu/netaxept.svc?wsdl';
  const WDSL_LIVE = 'https://epayment.nets.eu/netaxept.svc?wsdl';

  // Invoice endpoints
  const ENDPOINT_AVTALE_GIRO_REGISTER_TEST = 'https://pvu-test.nets.no/pvutest/atgtest.do';
  const ENDPOINT_AVTALE_GIRO_REGISTER_LIVE = 'https://epayment.nets.eu/Netaxept/Register.aspx';

  // Payments endpoints
  const TERMINAL_TEST_CREDIT_CARD         = 'https://test.epayment.nets.eu/Terminal/default.aspx';
  const TERMINAL_LIVE_CREDIT_CARD         = 'https://epayment.nets.eu/Terminal/default.aspx';

  /**
   * Commerce nets logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Token utils.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Event Dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  protected $payment_method;

  /**
   * Constructs a new NetsService object.
   *
   * @param \Psr\Log\LoggerInterface                                        $logger
   * @param \Drupal\Core\Utility\Token                                      $token
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   */
  public function __construct(LoggerInterface $logger, Token $token, ContainerAwareEventDispatcher $eventDispatcher) {
    $this->logger = $logger;
    $this->token = $token;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Get available NETS languages.
   *
   * @return array
   */
  public function getAvailableLanguages() {
    return array(
      'no_NO' => t('Norwegian'),
      'sv_SE' => t('Swedish'),
      'da_DK' => t('Danish'),
      'de_DE' => t('German'),
      'fi_FI' => t('Finnish'),
      'en_GB' => t('English'),
    );
  }

  /**
   * Performs a transaction Register at Nets.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   Payment method instance for the order.
   * @param \Drupal\commerce_price\Price $charge
   *   Charge array with amount and currency_code.
   * @param array $urls
   *   An array of URLs with keys 'return' and 'cancel'.
   *
   * @return string
   *   Transaction ID.
   *
   * @throws \Exception
   *   In case of unsuccessful registration exception will be thrown.
   */
  public function registerTransaction(Payment $payment, Price $charge, array $urls) {
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $payment_settings = $payment_gateway_plugin->getConfiguration();

    // Verify payment method
    $order = $payment->getOrder();
    $this->payment_method = $order->get('field_payment_method')->value;

    $wsdl = $this->wsdlUrl($payment_settings['mode']);

    $client = $this->initClient($wsdl);

    // Parameters in Order.
    $order_amount = ((float) $charge->getNumber() * 100);
    $order_currency_code = $charge->getCurrencyCode();
    $order_force_3dsecure = NULL;
    $order_goods = NULL;
    $order_number = $payment->getOrderId();
    $order_update_stored_payment_info = NULL;

    // Order object.
    $nets_order = new CommerceNetsOrder(
      $order_amount,
      $order_currency_code,
      $order_force_3dsecure,
      $order_goods,
      $order_number,
      $order_update_stored_payment_info
    );

    // Paramenters in Environment.
    $environment_language = NULL;
    $environment_os = NULL;
    $environment_webserviceplatform = 'PHP5';

    // Environment object.
    $nets_environment = new CommerceNetsEnvironment(
      $environment_language,
      $environment_os,
      $environment_webserviceplatform
    );

    // Parameters in Terminal.
    $terminal_auto_auth = NULL;
    $terminal_payment_methodlist = NULL;
    $terminal_language = $payment_settings['language'];

    // By default, we use no description for the terminal.
    $terminal_order_description = NULL;

    // Custom string provided for order_description. Replace it.
    if (!empty($payment_settings['order_description'])) {
      $order_description_replaced = $this->token->replace($payment_settings['order_description'], ['commerce-order' => $payment->getOrder()]);
      if ($payment_settings['order_description'] !== $order_description_replaced) {
        $terminal_order_description = $order_description_replaced;
      }
    }

    // Urls.
    $terminal_redirect_on_error = $urls['cancel'];
    $terminal_redirect_url = $urls['return'];

    // Terminal object.
    $nets_terminal = new CommerceNetsTerminal(
      $terminal_auto_auth,
      $terminal_payment_methodlist,
      $terminal_language,
      $terminal_order_description,
      $terminal_redirect_on_error,
      $terminal_redirect_url
    );

    // Parameters in Customer.
    $customer_address1 = NULL;
    $customer_address2 = NULL;
    $customer_company_name = NULL;
    $customer_company_registration_number = NULL;
    $customer_country = NULL;
    $customer_first_name = NULL;
    $customer_last_name = NULL;
    $customer_number = $payment->getOrder()->getCustomerId();
    $customer_email = $payment->getOrder()->getEmail();
    $customer_phone_number = NULL;
    $customer_postcode = NULL;
    $customer_social_security_number = NULL;
    $customer_town = NULL;

    // Customer object.
    $nets_customer = new CommerceNetsCustomer(
      $customer_address1,
      $customer_address2,
      $customer_company_name,
      $customer_company_registration_number,
      $customer_country,
      $customer_number,
      $customer_email,
      $customer_first_name,
      $customer_last_name,
      $customer_phone_number,
      $customer_postcode,
      $customer_social_security_number,
      $customer_town
    );

    // Parameters in Register Request.
    $nets_avtale_giro = NULL;
    $nets_card_info = NULL;
    $nets_description = NULL;
    $nets_dnbnor_direct_payment = NULL;
    $nets_micro_payment = NULL;
    $nets_recurring = NULL;
    $nets_service_type = NULL;

    // By default, let nets create a Transaction ID for us.
    $nets_transaction_id = NULL;
    $transaction_id_replaced = $this->token->replace($payment_settings['transaction_id'], ['commerce-order' => $payment->getOrder()]);
    if (!empty($transaction_id_replaced)) {
      $nets_transaction_id = $transaction_id_replaced;
    }
    $nets_transaction_recon_ref = NULL;

    // Register Request object.
    $nets_register_request = new CommerceNetsRegisterRequest(
      $nets_avtale_giro,
      $nets_card_info,
      $nets_customer,
      $nets_description,
      $nets_dnbnor_direct_payment,
      $nets_environment,
      $nets_micro_payment,
      $nets_order,
      $nets_recurring,
      $nets_service_type,
      $nets_terminal,
      $nets_transaction_id,
      $nets_transaction_recon_ref
    );

    // Soap parameters.
    $merchant_id = $payment_settings['merchantid'];
    $token = $payment_settings['netstoken'];

    // Soap parameters.
    $soap_parameters = array(
      "token"      => $token,
      "merchantId" => $merchant_id,
      "request"    => $nets_register_request,
    );
    try {
      $soap_result = $client->__call('Register', array("parameters" => $soap_parameters));
    }
    catch (\Exception $e) {
      $this->logException($e, 'Register');
      throw $e;
    }

    // RegisterResult.
    $register_result       = $soap_result->RegisterResult;
    $remote_transaction_id = $register_result->TransactionId;

    return $remote_transaction_id;
  }

  /**
   * Initialize NETS webservice client and include class file.
   *
   * @param string $wsdl
   *   WSDL path.
   *
   * @return \SoapClient
   *   Ssssoap client.
   * @throws \SoapFault
   */
  public function initClient($wsdl) {
    $client = new \SoapClient($wsdl, array(
      'trace' => TRUE,
      'exceptions' => TRUE,
    ));
    return $client;
  }

  /**
   * Returns the URL to the specified Nets WSDL server.
   *
   * @param string $environment
   *   Either test or live indicating which environment to get the URL for.
   *
   * @return string
   *   The URL to use to submit requests to the Nets server.
   */
  public function wsdlUrl($environment) {
    return $environment == 'live' ? self::WDSL_LIVE : self::WDSL_TEST;
  }

  /**
   * Returns the URL to the specified Nets terminal server.
   *
   * @param string $environment
   *   Either test or live indicating which environment to get the URL for.
   *
   * @param string $payment_method
   *
   * @return string
   *   The URL to use to submit requests to the Nets server.
   */
  public function terminalUrl($environment) {
    return $environment == 'live' ? self::TERMINAL_LIVE_CREDIT_CARD : self::TERMINAL_TEST_CREDIT_CARD;
  }

  /**
   * Helper function to log Exceptions from Nets.
   *
   * @param object $exception
   *   Exception object.
   * @param string $operation
   *   Operation we tried to perform at Nets (AUTH, SALE, ...).
   */
  public function logException(&$exception, $operation = '') {
    $message_vars = array('%operation' => $operation);

    if (isset($exception->detail)) {
      // We cycle to make it easy to find $type without knowing the real
      // exception.
      foreach ($exception->detail as $type => $e) {
        $message_vars += array(
          '%type' => $type,
          '%message' => isset($e->Message) ? $e->Message : '',
          '%response_code' => isset($e->Result) && isset($e->Result->ResponseCode) ? $e->Result->ResponseCode : '',
          '%response_source' => isset($e->Result) && isset($e->Result->ResponseSource) ? $e->Result->ResponseSource : '',
          '%response_text' => isset($e->Result) && isset($e->Result->ResponseText) ? $e->Result->ResponseText : '',
        );

        $this->logger->error('Nets failed, tried %operation. Type: %type, Message: %message, ResponseCode: %response_code, ResponseSource: %response_source and ResponseText: %response_text.', $message_vars);
      }
    }
    else {
      $this->logger->error('Nets failed, tried %operation. Exception: <pre>@exception</pre>', array(
        '@exception' => print_r($exception, TRUE),
        '%operation' => $operation,
      ));
    }
  }

  /**
   * Get information about transaction.
   *
   * @param array $payment_settings
   *   Payment method.
   * @param string $remote_id
   *   Transaction remote ID.
   * @param bool $reset
   *   Use from static cache if possible.
   *
   * @return object
   *
   * @throws \Exception
   */
  public function queryTransaction(array $payment_settings, $remote_id, $reset = TRUE) {

    $cache = &drupal_static(__FUNCTION__ . '_' . $remote_id);

    // If cached data can be used - use it.
    if (isset($cache) && !$reset) {
      return $cache;
    }

    $wsdl = $this->wsdlUrl($payment_settings['mode']);
    $client = $this->initClient($wsdl);

    $query_request = new CommerceNetsQueryRequest(
      $remote_id
    );

    // Soap parameters.
    $merchant_id = $payment_settings['merchantid'];
    $token       = $payment_settings['netstoken'];

    // Soap parameters.
    $soap_parameters = array(
      "token"      => $token,
      "merchantId" => $merchant_id,
      "request"    => $query_request,
    );

    try {
      $soap_result = $client->__call('Query', array("parameters" => $soap_parameters));
      $cache = $soap_result->QueryResult;
    }
    catch (\Exception $e) {
      $this->logException($e, 'Query');
      $cache = FALSE;
      throw $e;
    }
    return $cache;
  }

  /**
   * Performs a transaction Process at Nets.
   *
   * @param PaymentInterface $payment
   *   Payment entity.
   * @param int $action
   *   How much to capture/credit.
   * @param int $amount
   *
   * @return bool
   *   TRUE if success at processing the transaction at Nets, FALSE otherwise.
   * @throws \Exception
   */
  public function processTransaction(PaymentInterface $payment, $action, $amount = NULL) {
    $settings = $payment->getPaymentGateway()->getPluginConfiguration();
    $wsdl = $this->wsdlUrl($settings['mode']);
    $client = $this->initClient($wsdl);

    // Process parameters.
    $description = NULL;
    // Fallback to payment balance.
    $transaction_amount = isset($amount) ? $amount : (int) ($payment->getBalance()->getNumber() * 100);
    $transaction_id = $payment->getRemoteId();

    // Get the ref key form order.
    // @todo make sure that the ref key is also saved.
    $transaction_recon_ref = NULL;

    // Process object.
    $nets_process_request = new CommerceNetsProcessRequest(
      $description,
      $action,
      $transaction_amount,
      $transaction_id,
      $transaction_recon_ref
    );

    // Soap setup.
    $merchantid = $settings['merchantid'];
    $token = $settings['netstoken'];

    // Soap parameters.
    $soap_parameters = array(
      "token" => $token,
      "merchantId" => $merchantid,
      "request" => $nets_process_request,
    );

    // Dispatch modules can add/change params. ex. recon_ref
    // @todo: Commented out because of https://www.drupal.org/project/commerce_nets/issues/2990746#comment-12718374
    // $event = new TransactionEvent($payment, $action, $nets_process_request);
    // $this->eventDispatcher->dispatch(TransactionEvents::PROCESS, $event);

    try {
      $soap_result = $client->__call('Process', array("parameters" => $soap_parameters));
    }
    catch (\Exception $e) {
      $this->logException($e, 'Process');
      throw $e;
    }

    $process_result = $soap_result->ProcessResult;
    $payment->setRemoteState($process_result->ResponseCode);

    return TRUE;
  }

  /**
   * Performs a transaction Register at Nets for invoice method.
   *
   * @param \Drupal\commerce_payment\Entity\Payment $payment
   *   Payment method instance for the order.
   * @param \Drupal\commerce_price\Price $charge
   *   Charge array with amount and currency_code.
   * @param array $urls
   *   An array of URLs with keys 'return' and 'cancel'.
   *
   * @return string
   *   Transaction ID.
   *
   * @throws \Exception
   *   In case of unsuccessful registration exception will be thrown.
   */
  public function registerTransactionInvoice(Payment $payment, Price $charge, array $urls) {
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $payment_settings = $payment_gateway_plugin->getConfiguration();

    // Verify payment method
    $order = $payment->getOrder();
    $this->payment_method = $order->get('field_payment_method')->value;

    // Build the endpoint connection
    $endpoint = $this->invoiceEndpoint($payment_settings['mode'], 'register');

    //Build KID
    $kid = $this->buildKidNumber($order->id());

    $endpoint.=
      '?merchantId=' . $payment_settings['merchantid'] .
      '&url=' . $urls['return'] .
      '&account=' . $payment_settings['accountnumber'] .
      '&kid=' . $kid .
      '&name=' . $payment_settings['companyname'] .
      '&limit=' . str_replace(' NOK', '', $payment->getAmount()->__toString()) * 100;
    return $endpoint;
  }

  /**
   * Returns the URL to the specified Nets Endpoint server for invoice.
   *
   * @param string $environment
   *   Either test or live indicating which environment to get the URL for.
   *
   * @param        $operation_type
   *   If it is a register, process, query, terminal operation
   *
   * @return string
   *   The URL to use to submit requests to the Nets server.
   */
  public function invoiceEndpoint($environment, $operation_type) {
    // Get The type of operation
    switch ($operation_type) {
      case 'register':
        $url = $environment == 'live' ? self::ENDPOINT_AVTALE_GIRO_REGISTER_LIVE : self::ENDPOINT_AVTALE_GIRO_REGISTER_TEST;
        break;
      //case 'query':
      //  $url = $environment == 'live' ? self::QUERY_LIVE_AVTALE_GIRO : self::QUERY_TEST_AVTALE_GIRO;
      //  break;
      default:
        $url = $environment == 'live' ? self::WDSL_LIVE : self::WDSL_TEST;
        break;
    }

    return $url;
  }

  /**
   * @param $order_id
   *
   * @return string kid with 20 numbers
   */
  private function buildKidNumber($order_id) {
    // Order id is a 8 number length
    while(strlen($order_id) < 8) {
      $order_id = '0' . $order_id;
    }

    $count = 0;
    $sum = 0;

    for($i = strlen($order_id) - 1; $i >= 0; $i--) {
      // First multiply the Weighting
      // First char from right we wont to multiply by 2. First $i is 7, so it's even
      if($i % 2 == 0) {
        $count .= $order_id[$i] * 1;
      } else {
        $count .= $order_id[$i] * 2;
      }
    }

    //Then sum each char (Not number!!!)
    for($i = 0; $i < strlen($count); $i++) {
      $sum += $count[$i];
    }

    // Get the last char of sum
    $kid = substr($sum, -1);
    // If the single digit from sum is 0, the control digit will be 0
    $kid = $kid == 0 ? 0 : 10 - $kid;

    return '0000000' . $order_id . '0000' . $kid;
  }
}

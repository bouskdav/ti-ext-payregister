<?php

namespace Igniter\PayRegister\Payments;

use Admin\Classes\BasePaymentGateway;
use Admin\Models\Orders_model;
use ApplicationException;
use Exception;
use Igniter\Flame\Traits\EventEmitter;
use Igniter\PayRegister\Traits\PaymentHelpers;
use Omnipay\Omnipay;
use Redirect;
use Session;

class GPWebPay extends BasePaymentGateway
{
    use EventEmitter;
    use PaymentHelpers;

	//
	// Return URL - will be https://shopurl/gpwebpay_return_url?...
	//

    public function registerEntryPoints()
    {
        return [
            'gpwebpay_return_url' => 'processReturnUrl',
        ];
    }

	// ??? TODO
    public function getHiddenFields()
    {
        return [
            'gpwebpay_payment_method' => '',
            'gpwebpay_idempotency_key' => uniqid(),
        ];
    }

	// Testing mode for GP Webpay
    public function isTestMode()
    {
        return $this->model->transaction_mode != 'live';
    }

	// Public key
    public function getPublishableKey()
    {
        return $this->isTestMode() ? $this->model->test_publishable_key : $this->model->live_publishable_key;
    }

	// Private key
    public function getSecretKey()
    {
        return $this->isTestMode() ? $this->model->test_secret_key : $this->model->live_secret_key;
    }

	// Private key password
	public function getSecretKeyPassword()
	{
		return $this->isTestMode() ? $this->model->test_secret_key_password : $this->model->live_secret_key_password;
	}

	// Merchant number
	public function getMerchantNumber()
	{
		return $this->model->merchant_number;
	}

    // GPWebPay URL
    public function getGatewayUrl()
	{
        return $this-> isTestMode() ?
            "https://test.3dsecure.gpwebpay.com/pgw/order.do?" :
            "https://3dsecure.gpwebpay.com/pgw/order.do?";
	}

	// If is applicable
    public function isApplicable($total, $host)
    {
        return $host->order_total <= $total;
    }

    /**
	 * @param self $host
	 * @param \Main\Classes\MainController $controller
	 */
    public function beforeRenderPaymentForm($host, $controller)
    {
        //$controller->addCss('$/igniter/payregister/assets/gpwebpay.css', 'gpwebpay-css');
        //$controller->addJs('https://js.gpwebpay.com/v3/', 'gpwebpay-js');
        //$controller->addJs('$/igniter/payregister/assets/process.gpwebpay.js', 'process-gpwebpay-js');
    }

    /**
	 * Processes payment using passed data and redirect to payment gateway. Fired after submit button pressed.
	 *
	 * @param array $data
	 * @param \Admin\Models\Payments_model $host
	 * @param \Admin\Models\Orders_model $order
	 *
	 * @return bool|\Illuminate\Http\RedirectResponse
	 * @throws \ApplicationException
	 */
    public function processPaymentForm($data, $host, $order)
    {
        $this->validatePaymentMethod($order, $host);

		// build payment request
		// dev:
		// 'MERCHANTNUMBER'
		// 'OPERATION'
		// 'ORDERNUMBER'
        // 'AMOUNT'
        // 'CURRENCY'
		// 'DEPOSITFLAG'
        // 'MERORDERNUM'
        // 'URL'
        $fields = $this->getPaymentFormFields($order, $data);
        $fields['paymentMethod'] = array_get($data, 'gpwebpay_payment_method');

        try {
			/* Build signature input */
			$source_for_sign = $fields['MERCHANTNUMBER'] . "|" .
								$fields['OPERATION'] . "|" .
								$fields['ORDERNUMBER'] . "|" .
								$fields['AMOUNT'] . "|" .
								$fields['CURRENCY'] . "|" .
								$fields['DEPOSITFLAG'] . "|" .
								$fields['MERORDERNUM'] . "|" .
								$fields['URL'] . "|" .
								$fields['EMAIL'];

            // sign
            $digest = $this->sign($source_for_sign);

            //$url = $this->getGatewayUrl().'?';
            $url = $this->getGatewayUrl();
            $url .= 'MERCHANTNUMBER='.$fields['MERCHANTNUMBER'];
            $url .= '&OPERATION='.$fields['OPERATION'];
            $url .= '&ORDERNUMBER='.$fields['ORDERNUMBER'];
            $url .= '&AMOUNT='.$fields['AMOUNT'];
            $url .= '&CURRENCY='.$fields['CURRENCY'];
            $url .= '&DEPOSITFLAG='.$fields['DEPOSITFLAG'];
            $url .= '&MERORDERNUM='.$fields['MERORDERNUM'];
            $url .= '&URL='.urlencode($fields['URL']);
            $url .= '&EMAIL='.$fields['EMAIL'];
            $url .= '&DIGEST='.urlencode($digest);

            //Session::put('ti_payregister_gpwebpay_intent', $response->getPaymentIntentReference());

			//throw new ApplicationException('URL: '.$url);
			return Redirect::to($url);
        }
        catch (Exception $ex) {
            $order->logPaymentAttempt('Payment error -> '.$ex->getMessage(), 0, $fields, []);
            throw new ApplicationException('Sorry, there was an error processing your payment. Please try again later. '.$ex->getMessage());
        }
    }

	//
	// GPWebPay return method
	//
    public function processReturnUrl($params)
    {
        $hash = $params[0] ?? null;
        $redirectPage = input('redirect');
        $cancelPage = input('cancel');

        $order = $this->createOrderModel()->whereHash($hash)->first();

        try {
            if (!$hash OR !$order instanceof Orders_model)
                throw new ApplicationException('No order found');

            if (!strlen($redirectPage))
                throw new ApplicationException('No redirect page found');

            if (!strlen($cancelPage))
                throw new ApplicationException('No cancel page found');

            $paymentMethod = $order->payment_method;
            if (!$paymentMethod OR $paymentMethod->getGatewayClass() != static::class)
                throw new ApplicationException('No valid payment method found');

            $fields = $this->getPaymentFormFields($order);

            // Verify response
            $res_operation = input('OPERATION');
            $res_ordernumber = input('ORDERNUMBER');
            $res_merordernum = input('MERORDERNUM');
            $res_prcode = input('PRCODE');
            $res_srcode = input('SRCODE');
            $res_resulttext = input('RESULTTEXT');

            $digest = $res_operation.'|'.
                $res_ordernumber.'|'.
                $res_merordernum.'|'.
                $res_prcode.'|'.
                $res_srcode.'|'.
                $res_resulttext;

            $digest1 = $digest.'|'.$this->getMerchantNumber();

            $verified = $this->verify($digest, input('DIGEST'));
            $verified1 = $this->verify($digest1, input('DIGEST1'));

            // If not verified, cancel order
            if (!$verified || !$verified1)
			{
				throw new ApplicationException('Wrong signature. Might be malicious request.');
			}

            // Verify PRCODE and SRCODE
            // If not 0 and 0, cancel order
            if ($res_prcode != 0 || $res_srcode != 0) {
                throw new ApplicationException('Payment error: PRCODE '.$res_prcode.' ('.$this->getPRCodeMessage($res_prcode).') SRCODE '.$res_srcode.' ('.$this->getSRCodeMessage($res_prcode, $res_srcode).')');
			}

            // Else proceed as successfull
            $order->logPaymentAttempt('Payment successful', 1, $fields, $digest);
            $order->updateOrderStatus($paymentMethod->order_status, ['notify' => FALSE]);
            $order->markAsPaymentProcessed();

            return Redirect::to(page_url($redirectPage, [
                'id' => $order->getKey(),
                'hash' => $order->hash,
            ]));
        }
        catch (Exception $ex) {
            $order->logPaymentAttempt('Payment error -> '.$ex->getMessage(), 0, [], []);
            flash()->warning($ex->getMessage())->important();
        }

        return Redirect::to(page_url($cancelPage));
    }

    //
    // GPWebPay doesn't support payment profiles
    //

    /**
	 * {@inheritdoc}
	 */
    public function supportsPaymentProfiles()
    {
        return FALSE;
    }

	//
	// Build payment form fields
	//
    protected function getPaymentFormFields($order, $data = [])
    {
        $returnUrl = $this->makeEntryPointUrl('gpwebpay_return_url').'/'.$order->hash;
        $returnUrl .= '?redirect='.array_get($data, 'successPage').'&cancel='.array_get($data, 'cancelPage');

		$fields = [
			'MERCHANTNUMBER' => $this->getMerchantNumber(),
			'OPERATION' => 'CREATE_ORDER',
			'ORDERNUMBER' => date('YmdHis'),
            'AMOUNT' => number_format($order->order_total, 2, '.', '') * 100,
            'CURRENCY' => $this->getIsoCurrencyCode(currency()->getUserCurrency()),
			'DEPOSITFLAG' => 1,
            'MERORDERNUM' => $order->order_id,
            'URL' => $returnUrl,
            'EMAIL' => $order->email,
        ];

        $this->fireSystemEvent('payregister.gpwebpay.extendFields', [&$fields, $order, $data]);

        return $fields;
    }

    // Sign
    private function sign($text)
	{
        $pkeyid = openssl_get_privatekey($this->getSecretKey(), $this->getSecretKeyPassword());

        openssl_sign($text, $signature, $pkeyid);
        $signature = base64_encode($signature);
        openssl_free_key($pkeyid);

        return $signature;
	}

    // Verify
    private function verify($text, $signature)
	{
        $publicKey = $this->getPublishableKey();

        $pubkeyid = openssl_get_publickey($publicKey);
        $signature = base64_decode($signature);
        $result = openssl_verify($text, $signature, $pubkeyid);

        openssl_free_key($pubkeyid);

        return (($result == 1) ? true : false);
	}

    // ISO 4217 currency converter
    private function getIsoCurrencyCode($currency)
	{
		$currencies = [
            'CZK' => 203,
            'EUR' => 978,
            'GBP' => 826,
            'USD' => 840
        ];

        return $currencies[$currency];
	}

    // Translate PRCODE
    private function getPRCodeMessage($var_prcode)
	{
        if ($var_prcode == '0') {
            return $prcodes['msg_prcode_0'];
        } elseif ($var_prcode == '1') {
            return $prcodes['msg_prcode_1'];
		} elseif ($var_prcode == '2') {
            return $prcodes['msg_prcode_2'];
        } elseif ($var_prcode == '3') {
            return $prcodes['msg_prcode_3'];
        } elseif ($var_prcode == '4') {
            return $prcodes['msg_prcode_4'];
        } elseif ($var_prcode == '5') {
            return $prcodes['msg_prcode_5'];
        } elseif ($var_prcode == '11') {
            return $prcodes['msg_prcode_11'];
        } elseif ($var_prcode == '14') {
            return $prcodes['msg_prcode_15'];
        } elseif ($var_prcode == '15') {
            return $prcodes['msg_prcode_15'];
        } elseif ($var_prcode == '17') {
            return $prcodes['msg_prcode_17'];
        } elseif ($var_prcode == '18') {
            return $prcodes['msg_prcode_18'];
        } elseif ($var_prcode == '20') {
            return $prcodes['msg_prcode_20'];
        } elseif ($var_prcode == '25') {
            return $prcodes['msg_prcode_25'];
        } elseif ($var_prcode == '26') {
            return $prcodes['msg_prcode_26'];
        } elseif ($var_prcode == '27') {
            return $prcodes['msg_prcode_27'];
        } elseif ($var_prcode == '28') {
            return $prcodes['msg_prcode_28'];
        } elseif ($var_prcode == '30') {
            return $prcodes['msg_prcode_30'];
        } elseif ($var_prcode == '31') {
            return $prcodes['msg_prcode_31'];
        } elseif ($var_prcode == '35') {
            return $prcodes['msg_prcode_35'];
        } elseif ($var_prcode == '50') {
            return $prcodes['msg_prcode_50'];
        } elseif ($var_prcode == '200') {
            return $prcodes['msg_prcode_200'];
        } elseif ($var_prcode == '1000') {
            return $prcodes['msg_prcode_1000'];
        } else {
            return $prcodes['msg_prcode_unknown'];
        }
	}

    // Translate SRCODE
    private function getSRCodeMessage($var_prcode, $var_srcode)
	{
		if ($var_prcode == '0') {
            return $srcodes['msg_srcode_0'];
		} elseif ($var_prcode == '1' || $var_prcode == '2' || $var_prcode == '3' || $var_prcode == '4' || $var_prcode == '5' || $var_prcode == '15' || $var_prcode == '20') {
			if ($var_srcode == '1') {
				return $srcodes['msg_srcode_1'];
			} elseif ($var_srcode == '2') {
				return $srcodes['msg_srcode_2'];
			} elseif ($var_srcode == '6') {
				return $srcodes['msg_srcode_6'];
			} elseif ($var_srcode == '7') {
				return $srcodes['msg_srcode_7'];
			} elseif ($var_srcode == '8') {
				return $srcodes['msg_srcode_8'];
			} elseif ($var_srcode == '10') {
				return $srcodes['msg_srcode_10'];
			} elseif ($var_srcode == '11') {
				return $srcodes['msg_srcode_11'];
			} elseif ($var_srcode == '12') {
				return $srcodes['msg_srcode_12'];
			} elseif ($var_srcode == '18') {
				return $srcodes['msg_srcode_18'];
			} elseif ($var_srcode == '22') {
				return $srcodes['msg_srcode_22'];
			} elseif ($var_srcode == '24') {
				return $srcodes['msg_srcode_24'];
			} elseif ($var_srcode == '25') {
				return $srcodes['msg_srcode_25'];
			} elseif ($var_srcode == '26') {
				return $srcodes['msg_srcode_26'];
			} elseif ($var_srcode == '34') {
				return $srcodes['msg_srcode_34'];
			}
		} elseif ($var_prcode == '28') {
			if ($var_srcode == '3000') {
				return $srcodes['msg_srcode_3000'];
			} elseif ($var_srcode == '3001') {
				return $srcodes['msg_srcode_3001'];
			} elseif ($var_srcode == '3002') {
				return $srcodes['msg_srcode_3002'];
			} elseif ($var_srcode == '3004') {
				return $srcodes['msg_srcode_3004'];
			} elseif ($var_srcode == '3005') {
				return $srcodes['msg_srcode_3005'];
			} elseif ($var_srcode == '3006') {
				return $srcodes['msg_srcode_3006'];
			} elseif ($var_srcode == '3007') {
				return $srcodes['msg_srcode_3007'];
			} elseif ($var_srcode == '3008') {
				return $srcodes['msg_srcode_3008'];
			}
		} elseif ($var_prcode == '30') {
			if ($var_srcode == '1001') {
				return $srcodes['msg_srcode_1001'];
			} elseif ($var_srcode == '1001') {
				return $srcodes['msg_srcode_1002'];
			} elseif ($var_srcode == '1002') {
				return $srcodes['msg_srcode_1003'];
			} elseif ($var_srcode == '1004') {
				return $srcodes['msg_srcode_1004'];
			} elseif ($var_srcode == '1005') {
				return $srcodes['msg_srcode_1005'];
			}
		} else {
			return $srcodes['msg_srcode_unknown'];
		}
	}

    private $prcodes = [
        'msg_prcode_0' => 'OK',
        'msg_prcode_1' => 'Field too long',
        'msg_prcode_2' => 'Field too short',
        'msg_prcode_3' => 'Incorrect content of field',
        'msg_prcode_4' => 'Field is null',
        'msg_prcode_5' => 'Missing required field',
        'msg_prcode_11' => 'Unknown merchant',
        'msg_prcode_14' => 'Duplicate order number',
        'msg_prcode_15' => 'Object not found',
        'msg_prcode_17' => 'Amount to deposit exceeds approved amount',
        'msg_prcode_18' => 'Total sum of credited amounts exceeded deposited amount',
        'msg_prcode_20' => 'Object not in valid state for operation',
        'msg_prcode_25' => 'Operation not allowed for user',
        'msg_prcode_26' => 'Technical problem in connection to authorization center',
        'msg_prcode_27' => 'Incorrect order type',
        'msg_prcode_28' => 'Declined in 3D',
        'msg_prcode_30' => 'Declined in AC',
        'msg_prcode_31' => 'Wrong digest',
        'msg_prcode_35' => 'Session expired',
        'msg_prcode_50' => 'The cardholder canceled the payment',
        'msg_prcode_200' => 'Additional info request',
        'msg_prcode_1000' => 'Technical problem',
        'msg_prcode_unknown' => 'Unknown PR code',
    ];

    private $srcodes = [
        'msg_srcode_0' => 'No meaning',
        'msg_srcode_1' => 'ORDERNUMBER',
        'msg_srcode_2' => 'MERCHANTNUMBER',
        'msg_srcode_6' => 'AMOUNT',
        'msg_srcode_7' => 'CURRENCY',
        'msg_srcode_8' => 'DEPOSITFLAG',
        'msg_srcode_10' => 'MERORDERNUM',
        'msg_srcode_11' => 'CREDITNUMBER',
        'msg_srcode_12' => 'OPERATION',
        'msg_srcode_18' => 'BATCH',
        'msg_srcode_22' => 'ORDER',
        'msg_srcode_24' => 'URL',
        'msg_srcode_25' => 'MD',
        'msg_srcode_26' => 'DESC',
        'msg_srcode_34' => 'DIGEST',

        'msg_srcode_3000' => 'Declined in 3D. Cardholder not authenticated in 3D.',
        'msg_srcode_3001' => 'Authenticated',
        'msg_srcode_3002' => 'Not Authenticated in 3D. Issuer or Cardholder not participating in 3D.',
        'msg_srcode_3004' => 'Not Authenticated in 3D. Issuer not participating or Cardholder not enrolled.',
        'msg_srcode_3005' => 'Declined in 3D. Technical problem during Cardholder authentication.',
        'msg_srcode_3006' => 'Declined in 3D. Technical problem during Cardholder authentication.',
        'msg_srcode_3007' => 'Declined in 3D. Acquirer technical problem. Contact the merchant.',
        'msg_srcode_3008' => 'Declined in 3D. Unsupported card product.',

        'msg_srcode_1001' => 'Declined in AC, Card blocked',
        'msg_srcode_1002' => 'Declined in AC, Declined',
        'msg_srcode_1003' => 'Declined in AC, Card problem',
        'msg_srcode_1004' => 'Declined in AC, Technical problem in authorization process',
        'msg_srcode_1005' => 'Declined in AC, Account problem',
        'msg_srcode_unknown' => 'Unknown SR code',
    ];
}

<?php

namespace Ftg\Mpesa\controllers;

//use App\Http\Controllers\Controller;

use App\User;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Ftg\Mpesa\models\MpesaPaymentLog;
use Ftg\Mpesa\models\Payment;


class C2BController extends BaseController
{

    protected $dispatcher;


    /**
     * C2BController constructor.
     * @param Dispatcher $dispatcher
     */
    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function receiver(Request $request)
    {

        // Receive the Soap IPN from Safaricom
        $input = $request->getContent(); //getting the file input

        // check if $input is empty
        if (empty($input)) {
            return;
        }

        // package the data in an array with the type value for logging in mpesa_payment_logs_table table
        $data = ['content' => $input, 'type' => 'c2b'];

        // save
        MpesaPaymentLog::create($data);

        // initialize the DOMDocument  and create an object that we use to call loadXML and parse the XML
        $xml = new \DOMDocument();
        $xml->loadXML($input);// for c2b


        $data['phone_no'] = "+254" . substr(trim($xml->getElementsByTagName('MSISDN')->item(0)->nodeValue), -9);
        if ($xml->getElementsByTagName('KYCInfo')->length == 2) {
            $data['sender_first_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue;
            $data['sender_last_name'] = $xml->getElementsByTagName('KYCValue')->item(1)->nodeValue;
        } elseif ($xml->getElementsByTagName('KYCInfo')->length == 3) {
            $data['sender_first_name'] = $xml->getElementsByTagName('KYCValue')->item(0)->nodeValue;
            $data['sender_middle_name'] = $xml->getElementsByTagName('KYCValue')->item(1)->nodeValue;
            $data['sender_last_name'] = $xml->getElementsByTagName('KYCValue')->item(2)->nodeValue;
        }
        $data['transaction_id'] = $xml->getElementsByTagName('TransID')->item(0)->nodeValue;
        $data['amount'] = $xml->getElementsByTagName('TransAmount')->item(0)->nodeValue;
        $data['business_number'] = $xml->getElementsByTagName('BusinessShortCode')->item(0)->nodeValue;
        $data['acc_no'] = preg_replace('/\s+/', '', $xml->getElementsByTagName('BillRefNumber')->item(0)->nodeValue);
        $data['transaction_time'] = $xml->getElementsByTagName('TransTime')->item(0)->nodeValue;
        $data['transaction_type'] = $xml->getElementsByTagName('TransType')->item(0)->nodeValue; // The type of the transaction eg. Paybill, Buygoods etc,

        /**
         * save this in the payments table, but we first check if it exists (Safaricom sometimes send the notification twice)
         */

        if ($data['business_number'] != env('BusinessNumber')){
            $reply = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:c2b=\"http://cps.huawei.com/cpsinterface/c2bpayment\">
                       <soapenv:Header/>
                       <soapenv:Body>
                          <c2b:C2BPaymentValidationResult>
                            <ResultCode>C2B00015</ResultCode>
                           <ResultDesc>Business Number Error</ResultDesc>
                           <ThirdPartyTransID>0</ThirdPartyTransID>
                          </c2b:C2BPaymentValidationResult>
                       </soapenv:Body>
                    </soapenv:Envelope>
                    ";
            return $reply;
        }

        if ($data['amount'] > env('MaxAmount')){
            $reply = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:c2b=\"http://cps.huawei.com/cpsinterface/c2bpayment\">
                       <soapenv:Header/>
                       <soapenv:Body>
                          <c2b:C2BPaymentValidationResult>
                            <ResultCode>C2B00013</ResultCode>
                           <ResultDesc>Amount should be less than KES ".env('MaxAmount')."</ResultDesc>
                           <ThirdPartyTransID>0</ThirdPartyTransID>
                          </c2b:C2BPaymentValidationResult>
                       </soapenv:Body>
                    </soapenv:Envelope>
                    ";
            return $reply;
        }

        //check if the number is of a user
        $user = User::where('phone_number',$data['phone_no'])->first();
        if ($user != null){
            $transaction = Payment::whereTransactionId($data['transaction_id'])->first();
            if ($transaction === null) {
                $result = Payment::create($data);

                $payload = [
                    'payment' => $result
                ];

                // Fire the 'payment received' event
                $this->dispatcher->fire('c2b.received.payment', $payload);

                $reply = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:c2b=\"http://cps.huawei.com/cpsinterface/c2bpayment\">
                       <soapenv:Header/>
                       <soapenv:Body>
                          <c2b:C2BPaymentValidationResult>
                            <ResultCode>0</ResultCode>
                           <ResultDesc>Service processing successful</ResultDesc>
                           <ThirdPartyTransID>$result->id</ThirdPartyTransID>
                          </c2b:C2BPaymentValidationResult>
                       </soapenv:Body>
                    </soapenv:Envelope>
                    ";
                return $reply;
            }else{
                $reply = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:c2b=\"http://cps.huawei.com/cpsinterface/c2bpayment\">
                       <soapenv:Header/>
                       <soapenv:Body>
                          <c2b:C2BPaymentValidationResult>
                            <ResultCode>0</ResultCode>
                           <ResultDesc>Transaction ID Error</ResultDesc>
                           <ThirdPartyTransID>0</ThirdPartyTransID>
                          </c2b:C2BPaymentValidationResult>
                       </soapenv:Body>
                    </soapenv:Envelope>
                    ";
                return $reply;
            }
        }else{

                $reply = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:c2b=\"http://cps.huawei.com/cpsinterface/c2bpayment\">
                       <soapenv:Header/>
                       <soapenv:Body>
                          <c2b:C2BPaymentValidationResult>
                            <ResultCode>C2B00011</ResultCode>
                           <ResultDesc>Phone Number not Found</ResultDesc>
                           <ThirdPartyTransID>0</ThirdPartyTransID>
                          </c2b:C2BPaymentValidationResult>
                       </soapenv:Body>
                    </soapenv:Envelope>
                    ";
                return $reply;
        }


    }
}

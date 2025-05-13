<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

class PaymentXmlService
{
    private const DEFAULT_PAYMENT_TYPE = '99';
    private const DEFAULT_CHARGE_DETAILS = 'SHA';

    public function generatePaymentXml(array $data): string
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('PaymentRequestMessage');
        $doc->appendChild($root);

        // Transfer Info
        $transferInfo = $doc->createElement('TransferInfo');
        $this->appendChildIfNotEmpty($doc, $transferInfo, 'Reference', $data['reference']);
        $this->appendChildIfNotEmpty($doc, $transferInfo, 'Date', $data['date']);
        $this->appendChildIfNotEmpty($doc, $transferInfo, 'Amount', $data['amount']);
        $this->appendChildIfNotEmpty($doc, $transferInfo, 'Currency', $data['currency']);
        $this->appendChildIfNotEmpty($doc, $transferInfo, 'Bank', $data['bank'] ?? null);
        $root->appendChild($transferInfo);

        // Sender Info
        $senderInfo = $doc->createElement('SenderInfo');
        $this->appendChildIfNotEmpty($doc, $senderInfo, 'AccountNumber', $data['sender_account_number']);
        $root->appendChild($senderInfo);

        // Receiver Info
        $receiverInfo = $doc->createElement('ReceiverInfo');
        $this->appendChildIfNotEmpty($doc, $receiverInfo, 'BankCode', $data['receiver_bank_code']);
        $this->appendChildIfNotEmpty($doc, $receiverInfo, 'AccountNumber', $data['receiver_account_number']);
        $this->appendChildIfNotEmpty($doc, $receiverInfo, 'BeneficiaryName', $data['beneficiary_name']);
        $root->appendChild($receiverInfo);

        // Notes (only if present)
        if (!empty($data['notes'])) {
            $notes = $doc->createElement('Notes');
            foreach ($data['notes'] as $note) {
                $this->appendChildIfNotEmpty($doc, $notes, 'Note', $note);
            }
            $root->appendChild($notes);
        }

        // Payment Type (only if not default)
        if (($data['payment_type'] ?? self::DEFAULT_PAYMENT_TYPE) !== self::DEFAULT_PAYMENT_TYPE) {
            $this->appendChildIfNotEmpty($doc, $root, 'PaymentType', $data['payment_type']);
        }

        // Charge Details (only if not default)
        if (($data['charge_details'] ?? self::DEFAULT_CHARGE_DETAILS) !== self::DEFAULT_CHARGE_DETAILS) {
            $this->appendChildIfNotEmpty($doc, $root, 'ChargeDetails', $data['charge_details']);
        }

        return $doc->saveXML();
    }

    private function appendChildIfNotEmpty(DOMDocument $doc, DOMElement $parent, string $name, ?string $value): void
    {
        if (!empty($value)) {
            $element = $doc->createElement($name);
            $text = $doc->createTextNode($value);
            $element->appendChild($text);
            $parent->appendChild($element);
        }
    }
} 
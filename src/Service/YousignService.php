<?php

namespace App\Service;

use Exception;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class YousignService
{
    private const PATHFILE = __DIR__.'/../../public/'; 

    public function __construct(private HttpClientInterface $yousignClient){  
    }

    public function signatureRequest(): array
    {
        $response = $this->yousignClient->request(
            'POST',
            'signature_requests', 
            [
              'body' => <<<JSON
                      {
                        "name": "Contrat de location",
                        "delivery_mode": "email",
                        "timezone": "Europe/Paris"
                      }
                      JSON,
              'headers' => [
                  'Content-Type' => 'application/json',
              ],
            ]
        );

        $statusCode = $response->getStatusCode();

        if($statusCode !== 201){
            throw new \Exception("Error while creating signature request");
        }

        return $response->toArray();
    }


    public function uploadDocument(string $signatureRequestId, string $fileName): array
    {

        $formFields = [
            'nature' => 'signable_document',
            'file' => DataPart::fromPath(self::PATHFILE . $fileName, $fileName, 'application/pdf'),
        ];

        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        $response = $this->yousignClient->request(
            'POST', 
             sprintf('signature_requests/%s/documents', $signatureRequestId), 
             [
               'headers' => $headers,
               'body' => $formData->bodyToIterable(),
             ]
        );

        $statusCode = $response->getStatusCode();

        if($statusCode !== 201){
            throw new \Exception("Error while uploading Document");
        }

        return $response->toArray();
    }


    public function addSigner(
        string $signatureRequestId,
        string $documentId,
        string $email,
        string $prenom,
        string $nom,
    ): array
    {
        $response = $this->yousignClient->request(
            'POST', 
            sprintf('signature_requests/%s/signers', $signatureRequestId), 
            [
                'body' => <<<JSON
                       {
                           "info":{
                              "first_name":"$prenom",
                              "last_name":"$nom",
                              "email":"$email",
                              "locale":"fr"
                           },
                           "fields":[
                              {
                                 "type":"signature",
                                 "document_id": "$documentId",
                                 "page":1,
                                 "x":77,
                                 "y":581
                              }
                           ],
                           "signature_level":"electronic_signature",
                           "signature_authentication_mode":"no_otp"
                       }
                       JSON,
                'headers' => [
                'Content-Type' => 'application/json',
                ],
        ]);

        $statusCode = $response->getStatusCode();

        if($statusCode !== 201){
            throw new \Exception("Error while add Signer");
        }

        return $response->toArray();
    }


    public function activateSignatureRequest(string $signatureRequestId): array
    {     
        $response = $this->yousignClient->request(
            'POST', 
            sprintf('signature_requests/%s/activate', $signatureRequestId)
        );

        $statusCode = $response->getStatusCode();

        if($statusCode !== 201){
            throw new \Exception("Error while signature request");
        }

        return $response->toArray();
    }

}
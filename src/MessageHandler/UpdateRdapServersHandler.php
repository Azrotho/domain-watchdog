<?php

namespace App\MessageHandler;

use App\Message\UpdateRdapServers;
use App\Service\RDAPService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

#[AsMessageHandler]
final readonly class UpdateRdapServersHandler
{
    public function __construct(private RDAPService $RDAPService)
    {

    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface|Throwable
     */
    public function __invoke(UpdateRdapServers $message): void
    {
        /** @var Throwable[] $throws */
        $throws = [];
        try {
            $this->RDAPService->updateTldListIANA();
        } catch (Throwable $throwable) {
            $throws[] = $throwable;
        }
        try {
            $this->RDAPService->updateGTldListICANN();
        } catch (Throwable $throwable) {
            $throws[] = $throwable;
        }
        try {
            $this->RDAPService->updateRDAPServers();
        } catch (Throwable $throwable) {
            $throws[] = $throwable;
        }
        if (!empty($throws)) throw $throws[0];
    }
}

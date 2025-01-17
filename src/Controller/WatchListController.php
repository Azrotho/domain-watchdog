<?php

namespace App\Controller;

use App\Entity\Domain;
use App\Entity\DomainEntity;
use App\Entity\DomainEvent;
use App\Entity\User;
use App\Entity\WatchList;
use App\Repository\WatchListRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Eluceo\iCal\Domain\Entity\Attendee;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\Category;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Psr\Log\LoggerInterface;
use Sabre\VObject\EofException;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class WatchListController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
        private readonly WatchListRepository $watchListRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws \Exception
     */
    #[Route(
        path: '/api/watchlists',
        name: 'watchlist_create',
        defaults: [
            '_api_resource_class' => WatchList::class,
            '_api_operation_name' => 'create',
        ],
        methods: ['POST']
    )]
    public function createWatchList(Request $request): WatchList
    {
        $watchList = $this->serializer->deserialize($request->getContent(), WatchList::class, 'json', ['groups' => 'watchlist:create']);

        /** @var User $user */
        $user = $this->getUser();
        $watchList->setUser($user);

        /*
         * In the limited version, we do not want a user to be able to register the same domain more than once in their watchlists.
         * This policy guarantees the equal probability of obtaining a domain name if it is requested by several users.
         */
        if ($this->getParameter('limited_features')) {
            if ($watchList->getDomains()->count() >= (int) $this->getParameter('limit_max_watchlist_domains')) {
                $this->logger->notice('User {username} tried to create a Watchlist. However, the maximum number of domains has been reached for this Watchlist', [
                    'username' => $user->getUserIdentifier(),
                ]);
                throw new AccessDeniedHttpException('You have exceeded the maximum number of domain names allowed in this Watchlist');
            }

            $userWatchLists = $user->getWatchLists();
            if ($userWatchLists->count() >= (int) $this->getParameter('limit_max_watchlist')) {
                $this->logger->notice('User {username} tried to create a Watchlist. However, the maximum number of Watchlists has been reached.', [
                    'username' => $user->getUserIdentifier(),
                ]);
                throw new AccessDeniedHttpException('You have exceeded the maximum number of Watchlists allowed');
            }

            /** @var Domain[] $trackedDomains */
            $trackedDomains = $userWatchLists->reduce(fn (array $acc, WatchList $watchList) => [...$acc, ...$watchList->getDomains()->toArray()], []);

            /** @var Domain $domain */
            foreach ($watchList->getDomains()->getIterator() as $domain) {
                if (in_array($domain, $trackedDomains)) {
                    $this->logger->notice('User {username} tried to create a watchlist with domain name {ldhName}. However, it is forbidden to register the same domain name twice with limited mode.', [
                        'username' => $user->getUserIdentifier(),
                        'ldhName' => $domain->getLdhName(),
                    ]);

                    throw new AccessDeniedHttpException('It is forbidden to register the same domain name twice in your watchlists with limited mode.');
                }
            }
        }

        $this->logger->info('User {username} register a Watchlist ({token}).', [
            'username' => $user->getUserIdentifier(),
            'token' => $watchList->getToken(),
        ]);

        $this->em->persist($watchList);
        $this->em->flush();

        return $watchList;
    }

    /**
     * @throws ParseException
     * @throws EofException
     * @throws InvalidDataException
     * @throws \Exception
     */
    #[Route(
        path: '/api/watchlists/{token}/calendar',
        name: 'watchlist_calendar',
        defaults: [
            '_api_resource_class' => WatchList::class,
            '_api_operation_name' => 'calendar',
        ]
    )]
    public function getWatchlistCalendar(string $token): Response
    {
        /** @var WatchList $watchList */
        $watchList = $this->watchListRepository->findOneBy(['token' => $token]);

        $calendar = new Calendar();

        /** @var Domain $domain */
        foreach ($watchList->getDomains()->getIterator() as $domain) {
            $attendees = [];

            /** @var DomainEntity $entity */
            foreach ($domain->getDomainEntities()->toArray() as $entity) {
                $vCard = Reader::readJson($entity->getEntity()->getJCard());
                if (isset($vCard->EMAIL) && isset($vCard->FN)) {
                    $email = (string) $vCard->EMAIL;
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $attendees[] = (new Attendee(new EmailAddress($email)))->setDisplayName((string) $vCard->FN);
                }
            }

            /** @var DomainEvent $event */
            foreach ($domain->getEvents()->toArray() as $event) {
                $calendar->addEvent((new Event())
                    ->setLastModified(new Timestamp($domain->getUpdatedAt()))
                    ->setStatus(EventStatus::CONFIRMED())
                    ->setSummary($domain->getLdhName().' ('.$event->getAction().')')
                    ->addCategory(new Category($event->getAction()))
                    ->setAttendees($attendees)
                    ->setOccurrence(new SingleDay(new Date($event->getDate())))
                );
            }
        }

        $calendarResponse = (new CalendarFactory())->createCalendar($calendar);
        $calendarName = $watchList->getName();
        if (null !== $calendarName) {
            $calendarResponse->withProperty(new Property('X-WR-CALNAME', new TextValue($calendarName)));
        }

        return new Response($calendarResponse, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    #[Route(
        path: '/api/watchlists',
        name: 'watchlist_get_all_mine',
        defaults: [
            '_api_resource_class' => WatchList::class,
            '_api_operation_name' => 'get_all_mine',
        ],
        methods: ['GET']
    )]
    public function getWatchLists(): Collection
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getWatchLists();
    }
}

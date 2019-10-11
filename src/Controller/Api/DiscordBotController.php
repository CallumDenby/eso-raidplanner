<?php

/*
 * This file is part of the ESO Raidplanner project.
 * @copyright ESO Raidplanner.
 *
 * For the full license, see the license file distributed with this code.
 */

namespace App\Controller\Api;

use App\Controller\Checks\TalksWithDiscordBotController;
use App\Entity\EventAttendee;
use App\Exception\UnexpectedDiscordApiResponseException;
use App\Repository\DiscordGuildRepository;
use App\Repository\EventAttendeeRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\DiscordBotService;
use App\Service\GuildLoggerService;
use App\Utility\EsoClassUtility;
use App\Utility\EsoRoleUtility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Woeler\DiscordPhp\Message\AbstractDiscordMessage;
use Woeler\DiscordPhp\Message\DiscordEmbedsMessage;
use Woeler\DiscordPhp\Message\DiscordTextMessage;

/**
 * @Route("/api/discord", name="api_discord_")
 */
class DiscordBotController extends AbstractController implements TalksWithDiscordBotController
{
    /**
     * @var DiscordBotService
     */
    private $discordBotService;

    /**
     * @var DiscordGuildRepository
     */
    private $discordGuildRepository;

    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var EventAttendeeRepository
     */
    private $eventAttendeeRepository;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var GuildLoggerService
     */
    private $guildLoggerService;

    public function __construct(
        DiscordBotService $discordBotService,
        DiscordGuildRepository $discordGuildRepository,
        EventRepository $eventRepository,
        EventAttendeeRepository $eventAttendeeRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        GuildLoggerService $guildLoggerService
    ) {
        $this->discordBotService = $discordBotService;
        $this->discordGuildRepository = $discordGuildRepository;
        $this->eventRepository = $eventRepository;
        $this->eventAttendeeRepository = $eventAttendeeRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->guildLoggerService = $guildLoggerService;
    }

    /**
     * @Route("/bot", name="bot_entry_point")
     *
     * @param Request $request
     * @return Response
     */
    public function entryPoint(Request $request): Response
    {
        $command = $request->request->get('command');

        try {
            switch ($command) {
                case '!events':
                    $this->events($request);
                    break;
                case '!event':
                    $this->event($request);
                    break;
                case '!attend':
                    $this->attend($request);
                    break;
                case '!unattend':
                    $this->unattend($request);
                    break;
                default:
                    if (!empty($request->request->get('channelId'))) {
                        $this->replyWithText('Oops, something went wrong.', $request->request->get('channelId'));
                    }

                    return Response::create('', Response::HTTP_BAD_REQUEST);
            }
        } catch (UnexpectedDiscordApiResponseException $e) {
            // Something went wrong
            // ToDo
        }

        return Response::create('ok', Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @throws UnexpectedDiscordApiResponseException
     */
    protected function events(Request $request): void
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $request->request->get('guildId')]);
        $events = $this->eventRepository->findFutureEventsForGuild($guild);
        $user = $this->userRepository->findOneBy(['discordId' => $request->request->get('userId')]);
        $desc = '';
        foreach ($events as $event) {
            $desc .= $event->getId().': **'.$event->getName().'**'.PHP_EOL.$user->toUserTimeString($event->getStart()).PHP_EOL.PHP_EOL;
        }

        $message = (new DiscordEmbedsMessage())
            ->setTitle('Upcoming events')
            ->setAuthorIcon('https://cdn.discordapp.com/icons/'.$guild->getId().'/'.$guild->getIcon().'.png')
            ->setAuthorName($guild->getName())
            ->addField('Times displayed in your timezone', $user->getTimezone())
            ->setDescription($desc);
        $message->setContent($user->getDiscordMention());

        $this->replyWith($message, $request->request->get('channelId'));
    }

    /**
     * @param Request $request
     * @throws UnexpectedDiscordApiResponseException
     */
    public function event(Request $request): void
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $request->request->get('guildId')]);
        $event = $this->eventRepository->find(trim($request->request->get('query')));
        $user = $this->userRepository->findOneBy(['discordId' => $request->request->get('userId')]);
        if (null === $event || $event->getGuild()->getId() !== $guild->getId()) {
            $this->replyWithText('I don\'t know that event.', $request->request->get('channelId'));

            return;
        }

        $message = (new DiscordEmbedsMessage())
            ->setTitle($event->getName())
            ->setAuthorIcon('https://cdn.discordapp.com/icons/'.$guild->getId().'/'.$guild->getIcon().'.png')
            ->setDescription($event->getDescription());
        $message->setContent($user->getDiscordMention());
        foreach (EsoRoleUtility::toArray() as $roleId => $roleName) {
            $attendees = $event->getAttendeesByRole($roleId);
            if (0 < count($attendees)) {
                $text = '';
                foreach ($attendees as $attendee) {
                    $text .= $attendee->getUser()->getDiscordMention().PHP_EOL;
                }
                $message->addField($roleName, $text, true);
            }
        }

        $this->replyWith($message, $request->request->get('channelId'));
    }

    /**
     * @param Request $request
     * @throws UnexpectedDiscordApiResponseException
     */
    public function attend(Request $request): void
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $request->request->get('guildId')]);
        $data = explode(' ', trim($request->request->get('query')));
        $event = $this->eventRepository->find($data[0]);
        $class = EsoClassUtility::getClassIdByAlias($data[1] ?? '');
        $role = EsoRoleUtility::getRoleIdByAlias($data[2] ?? '');
        $user = $this->userRepository->findOneBy(['discordId' => $request->request->get('userId')]);
        if (null === $event || $event->getGuild()->getId() !== $guild->getId()) {
            $this->replyWithText(
                $user->getDiscordMention().' I don\'t know that event.',
                $request->request->get('channelId')
            );

            return;
        } elseif (null === $class) {
            $this->replyWithText(
                $user->getDiscordMention().' I don\'t know that class.',
                $request->request->get('channelId')
            );

            return;
        } elseif (null === $role) {
            $this->replyWithText(
                $user->getDiscordMention().' I don\'t know that role.',
                $request->request->get('channelId')
            );

            return;
        }

        $attendee = $this->eventAttendeeRepository->findOneBy(['user' => $user, 'event' => $event]);
        if (null === $attendee) {
            $attendee = (new EventAttendee())
                ->setUser($user)
                ->setEvent($event);
        }
        $attendee->setClass($class)
            ->setRole($role)
            ->setSets([]);

        $this->entityManager->persist($attendee);
        $this->entityManager->flush();

        $this->replyWithText(
            $user->getDiscordMention().' you are now attending '.$event->getName().' as a '.EsoClassUtility::getClassName($class).' '.EsoRoleUtility::getRoleName($role),
            $request->request->get('channelId')
        );
        $this->guildLoggerService->eventAttending($guild, $event, $attendee);
    }

    /**
     * @param Request $request
     * @throws UnexpectedDiscordApiResponseException
     */
    public function unattend(Request $request): void
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $request->request->get('guildId')]);
        $event = $this->eventRepository->find(trim($request->request->get('query')));
        $user = $this->userRepository->findOneBy(['discordId' => $request->request->get('userId')]);
        if (null === $event || $event->getGuild()->getId() !== $guild->getId()) {
            $this->replyWithText('I don\'t know that event.', $request->request->get('channelId'));

            return;
        }

        $attendee = $this->eventAttendeeRepository->findOneBy(['user' => $user, 'event' => $event]);
        if (null !== $attendee) {
            $this->guildLoggerService->eventUnattending($guild, $event, $attendee);
            $this->entityManager->remove($attendee);
            $this->entityManager->flush();
        }

        $this->replyWithText(
            $user->getDiscordMention().' you are no longer attending '.$event->getName(),
            $request->request->get('channelId')
        );
    }

    /**
     * @param AbstractDiscordMessage $message
     * @param string $chanelId
     * @throws UnexpectedDiscordApiResponseException
     */
    protected function replyWith(AbstractDiscordMessage $message, string $chanelId): void
    {
        if ($message instanceof DiscordEmbedsMessage) {
            $message->setFooterIcon('https://esoraidplanner.com/favicon/appicon.jpg');
            $message->setFooterText('ESO Raidplanner by Woeler');
            $message->setColor(9660137);
        }

        $this->discordBotService->sendMessage($chanelId, $message);
    }

    /**
     * @param string $text
     * @param string $channelId
     * @throws UnexpectedDiscordApiResponseException
     */
    protected function replyWithText(string $text, string $channelId): void
    {
        $message = new DiscordTextMessage();
        $message->setContent($text);

        $this->discordBotService->sendMessage($channelId, $message);
    }
}

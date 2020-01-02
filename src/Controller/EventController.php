<?php

/*
 * This file is part of the ESO Raidplanner project.
 * @copyright ESO Raidplanner.
 *
 * For the full license, see the license file distributed with this code.
 */

namespace App\Controller;

use App\Entity\CharacterPreset;
use App\Entity\Event;
use App\Entity\EventAttendee;
use App\Form\EventAttendeesStatusType;
use App\Repository\DiscordGuildRepository;
use App\Repository\EventAttendeeRepository;
use App\Repository\EventRepository;
use App\Security\Voter\EventVoter;
use App\Security\Voter\GuildVoter;
use App\Service\GuildLoggerService;
use App\Utility\EsoRoleUtility;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/guild", name="guild_")
 */
class EventController extends AbstractController
{
    /**
     * @var DiscordGuildRepository
     */
    private $discordGuildRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var EventAttendeeRepository
     */
    private $eventAttendeeRepository;

    /**
     * @var GuildLoggerService
     */
    private $guildLoggerService;

    public function __construct(
        DiscordGuildRepository $discordGuildRepository,
        EntityManagerInterface $entityManager,
        EventRepository $eventRepository,
        EventAttendeeRepository $eventAttendeeRepository,
        GuildLoggerService $guildLoggerService
    ) {
        $this->discordGuildRepository = $discordGuildRepository;
        $this->entityManager = $entityManager;
        $this->eventRepository = $eventRepository;
        $this->eventAttendeeRepository = $eventAttendeeRepository;
        $this->guildLoggerService = $guildLoggerService;
    }

    /**
     * @Route("/{guildId}/event/{eventId}/view", name="event_view")
     * @IsGranted("ROLE_USER")
     *
     * @param string $guildId
     * @param int $eventId
     * @param Request $request
     * @return Response
     */
    public function viewEvent(string $guildId, int $eventId, Request $request): Response
    {
        $event = $this->eventRepository->find($eventId);
        $this->denyAccessUnlessGranted(EventVoter::VIEW, $event);

        $attendee = $this->eventAttendeeRepository->findOneBy(['user' => $this->getUser()->getId(), 'event' => $eventId]);
        $attending = true;
        if (null === $attendee) {
            $attendee = new EventAttendee();
            $attending = false;
        }
        $form = $this->createForm(\App\Form\EventAttendeeType::class, $attendee, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(EventVoter::ATTEND, $event);
            if (!empty($form['preset']) && !empty($form['preset']->getData())) {
                /** @var CharacterPreset $preset */
                $preset = $form['preset']->getData();
                $attendee->setRole($preset->getRole())
                    ->setClass($preset->getClass())
                    ->setSets($preset->getSets()->toArray());
            }

            $attendee->setUser($this->getUser())
                ->setEvent($event);
            $this->entityManager->persist($attendee);
            $this->entityManager->flush();
            if (!$attending) {
                $this->guildLoggerService->eventAttending($event->getGuild(), $event, $attendee);
            }
            $this->addFlash('success', 'Event attendance updated.');

            return $this->redirectToRoute('guild_event_view', ['guildId' => $guildId, 'eventId' => $eventId]);
        }

        $attendeeForm = $this->createForm(
            EventAttendeesStatusType::class,
            null,
            ['attendees' => $event->getAttendees(), 'event' => $event]
        );

        return $this->render(
            'event/view.html.twig',
            [
                'event' => $event,
                'guild' => $this->discordGuildRepository->find($guildId),
                'form' => $form->createView(),
                'attending' => $attending,
                'roles' => EsoRoleUtility::toArray(),
                'attendeeForm' => $attendeeForm->createView(),
            ]
        );
    }

    /**
     * @Route("/{guildId}/event/create", name="event_create")
     * @IsGranted("ROLE_USER")
     *
     * @param string $guildId
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function createEvent(string $guildId, Request $request): Response
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $guildId]);
        $this->denyAccessUnlessGranted(GuildVoter::CREATE_EVENT, $guild);

        $event = new Event();
        $form = $this->createForm(\App\Form\EventType::class, $event, ['timezone' => $this->getUser()->getTimezone(), 'clock' => $this->getUser()->getClock()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event->setGuild($guild);
            $this->entityManager->persist($event);
            $this->entityManager->flush();
            $this->guildLoggerService->eventCreated($guild, $event);
            $this->addFlash('success', 'Event '.$event->getName().' created.');

            return $this->redirectToRoute('guild_view', ['guildId' => $guildId]);
        }

        return $this->render(
            'event/form.html.twig',
            [
                'form' => $form->createView(),
                'guild' => $guild,
            ]
        );
    }

    /**
     * @Route("/{guildId}/event/{eventId}/update", name="event_update")
     * @IsGranted("ROLE_USER")
     *
     * @param string $guildId
     * @param int $eventId
     * @param Request $request
     * @return Response
     */
    public function updateEvent(string $guildId, int $eventId, Request $request): Response
    {
        $guild = $this->discordGuildRepository->findOneBy(['id' => $guildId]);
        $event = $this->eventRepository->find($eventId);
        $this->denyAccessUnlessGranted(EventVoter::UPDATE, $event);

        $form = $this->createForm(\App\Form\EventType::class, $event, ['timezone' => $this->getUser()->getTimezone(), 'clock' => $this->getUser()->getClock()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($event);
            $this->entityManager->flush();
            $this->guildLoggerService->eventCreated($guild, $event);
            $this->addFlash('success', 'Event '.$event->getName().' updated.');

            return $this->redirectToRoute('guild_view', ['guildId' => $guildId]);
        }

        return $this->render(
            'event/form.html.twig',
            [
                'form' => $form->createView(),
                'guild' => $guild,
                'update' => true,
            ]
        );
    }

    /**
     * @Route("/{guildId}/event/{eventId}/delete", name="event_delete")
     * @IsGranted("ROLE_USER")
     *
     * @param string $guildId
     * @param int $eventId
     * @param Request $request
     * @return Response
     */
    public function deleteEvent(string $guildId, int $eventId, Request $request): Response
    {
        $event = $this->eventRepository->find($eventId);
        $this->denyAccessUnlessGranted(EventVoter::DELETE, $event);

        $this->guildLoggerService->eventDeleted($event->getGuild(), $event);
        $this->entityManager->remove($event);
        $this->entityManager->flush();
        $this->addFlash('success', 'Event was deleted.');

        return $this->redirectToRoute('guild_view', ['guildId' => $guildId]);
    }

    /**
     * @Route("/{guildId}/event/{eventId}/unattend", name="event_unattend")
     * @IsGranted("ROLE_USER")
     *
     * @param string $guildId
     * @param int $eventId
     * @param Request $request
     * @return Response
     */
    public function eventUnattend(string $guildId, int $eventId, Request $request): Response
    {
        $attendee = $this->eventAttendeeRepository->findOneBy(['user' => $this->getUser()->getId(), 'event' => $eventId]);
        $event = $this->eventRepository->find($eventId);
        $this->denyAccessUnlessGranted(EventVoter::UNATTEND, $event);

        $guid = $this->discordGuildRepository->find($guildId);

        if (null !== $attendee) {
            $this->guildLoggerService->eventUnattending($guid, $event, $attendee);
            $this->entityManager->remove($attendee);
            $this->entityManager->flush();

            $this->addFlash('success', 'You are no longer attending this event.');
        }

        return $this->redirectToRoute('guild_event_view', ['guildId' => $guildId, 'eventId' => $eventId]);
    }

    /**
     * @Route("/{guildId}/event/{eventId}/attendeestatuschange", name="event_attendee_status_change")
     * @IsGranted("ROLE_USER")
     *
     * @param Request $request
     * @param string $guildId
     * @param int $eventId
     * @return Response
     */
    public function changeAttendeeStatus(Request $request, string $guildId, int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        $this->denyAccessUnlessGranted(EventVoter::CHANGE_ATTENDEE_STATUS, $event);

        $form = $this->createForm(
            EventAttendeesStatusType::class,
            null,
            ['attendees' => $event->getAttendees(), 'event' => $event]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $status = EventAttendee::STATUS_ATTENDING;
            $delete = false;
            if ($form->get('confirm')->isClicked()) {
                $status = EventAttendee::STATUS_CONFIRMED;
            }
            if ($form->get('reserve')->isClicked()) {
                $status = EventAttendee::STATUS_RESERVE;
            }
            if ($form->get('delete')->isClicked()) {
                $delete = true;
            }
            foreach ($form->getData() as $key => $value) {
                if (true !== $value) {
                    continue;
                }
                $attendee = $this->eventAttendeeRepository->findOneBy(['id' => str_replace('attendee_', '', $key), 'event' => $event]);
                if (null === $attendee) {
                    continue;
                }
                if ($delete) {
                    $this->guildLoggerService->eventUnattending($event->getGuild(), $event, $attendee);
                    $this->entityManager->remove($attendee);
                } else {
                    $attendee->setStatus($status);
                    $this->entityManager->persist($attendee);
                }
            }
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('guild_event_view', ['guildId' => $guildId, 'eventId' => $eventId]);
    }
}

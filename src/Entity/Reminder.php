<?php

/*
 * This file is part of the ESO Raidplanner project.
 * @copyright ESO Raidplanner.
 *
 * For the full license, see the license file distributed with this code.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity()
 */
class Reminder
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     * @Assert\NotNull()
     * @Assert\NotBlank()
     * @Assert\Length(min=1,max=200)
     */
    private $name;

    /**
     * @ManyToOne(targetEntity="DiscordChannel")
     * @ORM\JoinColumn(name="discord_channel_id", referencedColumnName="id", nullable=true,onDelete="SET NULL")
     * @var DiscordChannel
     */
    private $channel;

    /**
     * @ManyToOne(targetEntity="DiscordGuild", inversedBy="reminders")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @var DiscordGuild
     */
    private $guild;

    /**
     * @ORM\Column(type="text")
     * @var string
     * @Assert\Length(max=2000)
     */
    private $text;

    /**
     * @ORM\Column(type="integer")
     * @var int
     * @Assert\NotNull()
     * @Assert\Positive()
     */
    private $minutesToTrigger;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     * @Assert\NotNull()
     */
    private $detailedInfo;

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     * @Assert\NotNull()
     */
    private $pingAttendees;

    public function __construct()
    {
        $this->detailedInfo = false;
        $this->pingAttendees = false;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Reminder
     */
    public function setId(int $id): Reminder
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Reminder
     */
    public function setName(string $name): Reminder
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return DiscordChannel
     */
    public function getChannel(): ?DiscordChannel
    {
        return $this->channel;
    }

    /**
     * @param DiscordChannel $channel
     * @return Reminder
     */
    public function setChannel(DiscordChannel $channel): Reminder
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @return DiscordGuild
     */
    public function getGuild(): ?DiscordGuild
    {
        return $this->guild;
    }

    /**
     * @param DiscordGuild $guild
     * @return Reminder
     */
    public function setGuild(DiscordGuild $guild): Reminder
    {
        $this->guild = $guild;

        return $this;
    }

    /**
     * @return string
     */
    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * @param string $text
     * @return Reminder
     */
    public function setText(string $text): Reminder
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return int
     */
    public function getMinutesToTrigger(): ?int
    {
        return $this->minutesToTrigger;
    }

    /**
     * @param int $minutesToTrigger
     * @return Reminder
     */
    public function setMinutesToTrigger(int $minutesToTrigger): Reminder
    {
        $this->minutesToTrigger = $minutesToTrigger;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDetailedInfo(): bool
    {
        return $this->detailedInfo;
    }

    /**
     * @param bool $detailedInfo
     * @return Reminder
     */
    public function setDetailedInfo(bool $detailedInfo): Reminder
    {
        $this->detailedInfo = $detailedInfo;

        return $this;
    }

    /**
     * @return bool
     */
    public function isPingAttendees(): bool
    {
        return $this->pingAttendees;
    }

    /**
     * @param bool $pingAttendees
     * @return Reminder
     */
    public function setPingAttendees(bool $pingAttendees): Reminder
    {
        $this->pingAttendees = $pingAttendees;

        return $this;
    }

    public function __toString()
    {
        return $this->name.' ('.$this->guild->getName().')';
    }
}

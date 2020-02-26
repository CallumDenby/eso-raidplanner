<?php

/*
 * This file is part of the ESO Raidplanner project.
 * @copyright ESO Raidplanner.
 *
 * For the full license, see the license file distributed with this code.
 */

namespace App\Entity;

use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks
 */
class User implements UserInterface
{
    public const PATREON_NONE = 0;

    public const PATREON_BRONZE = 1;

    public const PATREON_SILVER = 2;

    public const PATREON_GOLD = 3;

    public const PATREON_RUBY = 4;

    public const PATREON = [
        self::PATREON_NONE => 'None',
        self::PATREON_BRONZE => 'Bronze',
        self::PATREON_SILVER => 'Silver',
        self::PATREON_GOLD => 'Gold',
        self::PATREON_RUBY => 'Ruby',
    ];

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
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=40)
     * @var string
     */
    private $discordDiscriminator;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $avatar;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     */
    private $discordId;

    /**
     * @OneToMany(targetEntity="DiscordGuild", mappedBy="owner")
     * @var Collection|DiscordGuild[]
     */
    private $discordGuilds;

    /**
     * @ORM\OneToMany(targetEntity="GuildMembership", mappedBy="user")
     * @var Collection|GuildMembership[]
     */
    private $guildMemberships;

    /**
     * @ORM\OneToMany(targetEntity="EventAttendee", mappedBy="user")
     * @var Collection|EventAttendee[]
     */
    private $events;

    /**
     * @ORM\Column(type="integer")
     * @var int
     * @Assert\NotNull()
     * @Assert\Positive()
     */
    private $clock = 24;

    /**
     * @ORM\Column(type="string", length=255)
     * @var string
     * @Assert\NotNull()
     * @Assert\NotBlank()
     */
    private $timezone = 'UTC';

    /**
     * @ORM\Column(type="boolean")
     * @var bool
     * @Assert\NotNull()
     */
    private $darkmode = false;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $discordToken;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $discordRefreshToken;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $discordTokenExpirationDate;

    /**
     * @ORM\Column(type="integer")
     */
    private $patreonMembership = 0;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotNull()
     * @Assert\PositiveOrZero()
     * 1 defaults to Monday, 0 is Sunday
     */
    private $firstDayOfWeek = 1;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CharacterPreset", mappedBy="user", orphanRemoval=true)
     * @OrderBy({"name" = "ASC"})
     */
    private $characterPresets;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $icalId;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->guildMemberships = new ArrayCollection();
        $this->discordGuilds = new ArrayCollection();
        $this->characterPresets = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->username.'#'.$this->discordDiscriminator;
    }

    /**
     * @param array $roles
     * @return $this
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return string|null The encoded password if any
     */
    public function getPassword()
    {
        return null;
    }

    /**
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    public function eraseCredentials()
    {
        return;
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
     * @return User
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getDiscordDiscriminator(): string
    {
        return $this->discordDiscriminator;
    }

    /**
     * @param string $discordDiscriminator
     * @return User
     */
    public function setDiscordDiscriminator(string $discordDiscriminator): self
    {
        $this->discordDiscriminator = $discordDiscriminator;

        return $this;
    }

    /**
     * @return string
     */
    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    /**
     * @return string
     */
    public function getFullAvatarUrl(): string
    {
        if (null !== $this->avatar && 'unknown' !== $this->avatar) {
            return 'https://cdn.discordapp.com/avatars/'.$this->discordId.'/'.$this->avatar.'.png';
        }

        return '/build/images/default_avatar.png';
    }

    /**
     * @param string $avatar
     * @return User
     */
    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * @return string
     */
    public function getDiscordId(): string
    {
        return $this->discordId;
    }

    /**
     * @param string $discordId
     * @return User
     */
    public function setDiscordId(string $discordId): self
    {
        $this->discordId = $discordId;

        return $this;
    }

    /**
     * @param string $username
     * @return User
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDarkmode()
    {
        return $this->darkmode;
    }

    /**
     * @param mixed $darkmode
     * @return User
     */
    public function setDarkmode($darkmode)
    {
        $this->darkmode = $darkmode;

        return $this;
    }

    /**
     * @return string
     */
    public function getDiscordToken(): string
    {
        return $this->discordToken;
    }

    /**
     * @param string $discordToken
     * @return User
     */
    public function setDiscordToken(string $discordToken): User
    {
        $this->discordToken = $discordToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getDiscordRefreshToken(): string
    {
        return $this->discordRefreshToken;
    }

    /**
     * @param string $discordRefreshToken
     * @return User
     */
    public function setDiscordRefreshToken(string $discordRefreshToken): User
    {
        $this->discordRefreshToken = $discordRefreshToken;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDiscordGuilds(): Collection
    {
        return $this->discordGuilds;
    }

    /**
     * @return Collection
     */
    public function getActiveDiscordGuilds(): Collection
    {
        return $this->discordGuilds->filter(static function (DiscordGuild $discordGuild) {
            return $discordGuild->isActive();
        });
    }

    /**
     * @param Collection $discordGuilds
     * @return User
     */
    public function setDiscordGuilds(Collection $discordGuilds): self
    {
        $this->discordGuilds = $discordGuilds;

        return $this;
    }

    /**
     * @return Collection|GuildMembership[]
     */
    public function getGuildMemberships(): Collection
    {
        return $this->guildMemberships;
    }

    /**
     * @return Collection
     */
    public function getActiveGuildMemberships(): Collection
    {
        $active = $this->guildMemberships->filter(static function (GuildMembership $guildMembership) {
            return $guildMembership->getGuild()->isActive();
        });
        $iterator = $active->getIterator();
        $iterator->uasort(static function ($a, $b) {
            return strcmp(strtolower($a->getGuild()->getName()), strtolower($b->getGuild()->getName()));
        });

        return new ArrayCollection(iterator_to_array($iterator));
    }

    public function getGuildNickname(DiscordGuild $guild): string
    {
        $active = $this->guildMemberships->filter(static function (GuildMembership $guildMembership) use ($guild) {
            return $guildMembership->getGuild()->getId() === $guild->getId();
        });

        return $active->first()->getNickname();
    }

    /**
     * @param Collection $guildMemberships
     * @return User
     */
    public function setGuildMemberships(Collection $guildMemberships): self
    {
        $this->guildMemberships = $guildMemberships;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param mixed $events
     * @return User
     */
    public function setEvents($events)
    {
        $this->events = $events;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getClock()
    {
        return $this->clock;
    }

    /**
     * @param mixed $clock
     * @return User
     */
    public function setClock($clock)
    {
        $this->clock = $clock;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @param mixed $timezone
     * @return User
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param DateTime $dateTime
     * @return string
     */
    public function toUserTimeString(DateTime $dateTime): string
    {
        $dateTime->setTimezone(new DateTimeZone($this->timezone ?? 'UTC'));

        if (12 === $this->clock) {
            return $dateTime->format('F jS g:ia');
        }

        return $dateTime->format('F jS H:i');
    }

    /**
     * @return string
     */
    public function getDiscordMention(): string
    {
        return '<@'.$this->getDiscordId().'>';
    }

    public function getDiscordTokenExpirationDate(): ?\DateTimeInterface
    {
        return $this->discordTokenExpirationDate;
    }

    public function setDiscordTokenExpirationDate(\DateTimeInterface $discordTokenExpirationDate): self
    {
        $this->discordTokenExpirationDate = $discordTokenExpirationDate;

        return $this;
    }

    public function getPatreonMembership(): ?int
    {
        return $this->patreonMembership;
    }

    public function setPatreonMembership(int $patreonMembership): self
    {
        $this->patreonMembership = $patreonMembership;

        return $this;
    }

    public function getFirstDayOfWeek(): ?int
    {
        return $this->firstDayOfWeek;
    }

    public function setFirstDayOfWeek(int $firstDayOfWeek): self
    {
        $this->firstDayOfWeek = $firstDayOfWeek;

        return $this;
    }

    /**
     * @return Collection|CharacterPreset[]
     */
    public function getCharacterPresets(): Collection
    {
        return $this->characterPresets;
    }

    public function addCharacterPreset(CharacterPreset $characterPreset): self
    {
        if (!$this->characterPresets->contains($characterPreset)) {
            $this->characterPresets[] = $characterPreset;
            $characterPreset->setUser($this);
        }

        return $this;
    }

    public function removeCharacterPreset(CharacterPreset $characterPreset): self
    {
        if ($this->characterPresets->contains($characterPreset)) {
            $this->characterPresets->removeElement($characterPreset);
            // set the owning side to null (unless already changed)
            if ($characterPreset->getUser() === $this) {
                $characterPreset->setUser(null);
            }
        }

        return $this;
    }

    public function getIcalId(): ?string
    {
        return $this->icalId;
    }

    public function setIcalId(?string $icalId): self
    {
        $this->icalId = $icalId;

        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function generateIcalId(): void
    {
        if (null === $this->getIcalId()) {
            $this->setIcalId(bin2hex(random_bytes(20)));
        }
    }
}

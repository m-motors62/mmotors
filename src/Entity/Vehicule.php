<?php

namespace App\Entity;

use App\Repository\VehiculeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehiculeRepository::class)]
class Vehicule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $marque = null;

    #[ORM\Column(length: 100)]
    private ?string $modele = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $motorisation = null;

    #[ORM\Column]
    private ?int $kilometrage = null;

    #[ORM\Column]
    private ?float $prix = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column]
    private ?bool $assurance = null;

    #[ORM\Column]
    private ?bool $assistance = null;

    #[ORM\Column]
    private ?bool $entretien = null;

    #[ORM\Column]
    private ?bool $controleTechnique = null;

    #[ORM\Column(length: 15, unique: true)]
    private ?string $immatriculation = null;

    /**
     * @var Collection<int, VehiculePhoto>
     */
    #[ORM\OneToMany(targetEntity: VehiculePhoto::class, mappedBy: 'vehicule', orphanRemoval: true)]
    private Collection $vehiculePhotos;

    public function __construct()
    {
        $this->vehiculePhotos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;

        return $this;
    }

    public function getMotorisation(): ?string
    {
        return $this->motorisation;
    }

    public function setMotorisation(?string $motorisation): static
    {
        $this->motorisation = $motorisation;

        return $this;
    }

    public function getKilometrage(): ?int
    {
        return $this->kilometrage;
    }

    public function setKilometrage(int $kilometrage): static
    {
        $this->kilometrage = $kilometrage;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function isAssurance(): ?bool
    {
        return $this->assurance;
    }

    public function setAssurance(bool $assurance): static
    {
        $this->assurance = $assurance;

        return $this;
    }

    public function isAssistance(): ?bool
    {
        return $this->assistance;
    }

    public function setAssistance(bool $assistance): static
    {
        $this->assistance = $assistance;

        return $this;
    }

    public function isEntretien(): ?bool
    {
        return $this->entretien;
    }

    public function setEntretien(bool $entretien): static
    {
        $this->entretien = $entretien;

        return $this;
    }

    public function isControleTechnique(): ?bool
    {
        return $this->controleTechnique;
    }

    public function setControleTechnique(bool $controleTechnique): static
    {
        $this->controleTechnique = $controleTechnique;

        return $this;
    }

    public function getImmatriculation(): ?string
    {
        return $this->immatriculation;
    }

    public function setImmatriculation(string $immatriculation): static
    {
        $this->immatriculation = $immatriculation;

        return $this;
    }

    /**
     * @return Collection<int, VehiculePhoto>
     */
    public function getVehiculePhotos(): Collection
    {
        return $this->vehiculePhotos;
    }

    public function addVehiculePhoto(VehiculePhoto $vehiculePhoto): static
    {
        if (!$this->vehiculePhotos->contains($vehiculePhoto)) {
            $this->vehiculePhotos->add($vehiculePhoto);
            $vehiculePhoto->setVehicule($this);
        }

        return $this;
    }

    public function removeVehiculePhoto(VehiculePhoto $vehiculePhoto): static
    {
        if ($this->vehiculePhotos->removeElement($vehiculePhoto)) {
            // set the owning side to null (unless already changed)
            if ($vehiculePhoto->getVehicule() === $this) {
                $vehiculePhoto->setVehicule(null);
            }
        }

        return $this;
    }
}

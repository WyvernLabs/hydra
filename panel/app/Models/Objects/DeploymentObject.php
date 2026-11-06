<?php

namespace Pterodactyl\Models\Objects;

class DeploymentObject
{
    private bool $dedicated = false;

    private array $locations = [];

    private array $ports = [];

    // Hydra: nodes to deploy to besides the locations, also non-public ones.
    private array $nodes = [];

    public function isDedicated(): bool
    {
        return $this->dedicated;
    }

    public function setDedicated(bool $dedicated): self
    {
        $this->dedicated = $dedicated;

        return $this;
    }

    public function getLocations(): array
    {
        return $this->locations;
    }

    public function setLocations(array $locations): self
    {
        $this->locations = $locations;

        return $this;
    }

    public function getPorts(): array
    {
        return $this->ports;
    }

    public function setPorts(array $ports): self
    {
        $this->ports = $ports;

        return $this;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function setNodes(array $nodes): self
    {
        $this->nodes = $nodes;

        return $this;
    }
}

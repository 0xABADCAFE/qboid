<?php

declare(strict_types=1);

include '../profiling.php';

interface CuboidRotation
{
    const
        ROT_LWH = 0,
        ROT_WLH = 1,
        ROT_WHL = 2,
        ROT_HWL = 3,
        ROT_LHW = 4,
        ROT_HLW = 5
    ;
}

trait CuboidMaterialisedProperties
{
    /** @var string $signature */
    /** @var float $volume */
    /** @var float irregularity */
    /** @var Cuboid[] rotations */

    public function __get(string $property)
    {
        switch ($property) {
            case 'signature':
                return $this->signature = sprintf("%gx%gx%g", $this->length, $this->width, $this->height);
            case 'volume':
                return $this->volume = (float)($this->length * $this->width * $this->height);
            case 'irregularity':
                $idealEdge = pow($this->volume, 1/3);
                return $this->irregularity = (float)(
                    (($this->length - $idealEdge)**2) +
                    (($this->width  - $idealEdge)**2) +
                    (($this->height - $idealEdge)**2)
                );
            case 'rotations':
                return $this->rotations = [
                    CuboidRotation::ROT_LWH => new Cuboid($this->length, $this->width,  $this->height),
                    CuboidRotation::ROT_WLH => new Cuboid($this->width,  $this->length, $this->height),
                    CuboidRotation::ROT_WHL => new Cuboid($this->width,  $this->height, $this->length),
                    CuboidRotation::ROT_HWL => new Cuboid($this->height, $this->width,  $this->length),
                    CuboidRotation::ROT_LHW => new Cuboid($this->length, $this->height, $this->width),
                    CuboidRotation::ROT_HLW => new Cuboid($this->height, $this->length, $this->width)
                ];
            default:
                return null;
        }
    }
}

class Cuboid
{
    public
        $length,
        $width,
        $height
    ;

    use CuboidMaterialisedProperties;

    public function __construct(float $l, float $w, float $h)
    {
        $this->length = $l;
        $this->width  = $w;
        $this->height = $h;
    }

    public function __toString() : string
    {
        return $this->signature;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

class RegularisedCuboid extends Cuboid
{
    public function __construct(float $l, float $w, float $h)
    {
        $dim = [$l, $w, $h];
        sort($dim);
        parent::__construct($dim[2], $dim[1], $dim[0]);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

class CuboidPacker
{
    public function best(RegularisedCuboid $input, Cuboid $container)
    {
        $bestCount = 0;
        $rotation = null;
        $containerVol = $container->volume;
        foreach ($input->rotations as $rotationType => $rotated) {
            $count      = $this->evaluate($rotated, $container);
            if ($count > $bestCount) {
                $bestCount = $count;
                $rotation  = $rotated;
            }
        }

        return (object)[
            'count'      => $count,
            'rotation'   => ($rotated ? $rotated->signature : "N/A"),
            'efficiency' => ($rotated ? 100*(($count * $rotated->volume) / $containerVol) : 0)
        ];
    }

    private function evaluate(Cuboid $input, Cuboid $output) : int
    {
        return (int)($output->length / $input->length) *
               (int)($output->width / $input->width) *
               (int)($output->height / $input->height);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$containers = [];
for ($i = 0; $i<10; $i++) {
    $containers[] = new Cuboid(mt_rand(40, 80), mt_rand(50, 100), mt_rand(60, 120));
}

$boxes = [];
for ($i = 0; $i<100; $i++) {
    $boxes[] = new RegularisedCuboid(mt_rand(1, 30), mt_rand(1, 30), mt_rand(1, 30));
}

$packer = new CuboidPacker();

foreach ($containers as $container) {

    echo
        "Container Cuboid :", $container,
        " [Volume: ", $cuboid->volume,
        ", Irregularity: ", $cuboid->irregularity,
        "]\n";


    foreach ($boxes as $i => $cuboid) {
        $best   = $packer->best($cuboid, $container);
        echo
            "Test ", $i,
            ": Orientation test for Cuboid: ", $cuboid,
            " [Volume: ", $cuboid->volume,
            ", Irregularity: ", $cuboid->irregularity,
            "] Count: ", $best->count,
            ", Efficiency: ", $best->efficiency,
            "% [when rotated as ", $best->rotation,
            "]\n";
    }

}

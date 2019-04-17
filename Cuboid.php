<?php

declare(strict_types=1);

//include '../profiling.php';

/**
 * CuboidRotation interface
 *
 * Enumeration of 6 distinct, axis aligned, rotations for an arbitrary Cuboid
 */
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

/**
 * CuboidMaterialisedProperties trait
 *
 * Mixin for Cuboid that adds a set of lazy evaluated member properties when first accessed.
 */
trait CuboidMaterialisedProperties
{
    /** @var string $signature */
    /** @var float $volume */
    /** @var float irregularity */
    /** @var Cuboid[] rotations */

    /**
     * Magic __get()
     *
     * When one of the materialised properties is first requested (and does not exist on the instance) this
     * call intercepts and generates the requested property.
     *
     * @param string $property
     * @return string|float|Cuboid[]
     */
    public function __get(string $property)
    {
        switch ($property) {
            case 'signature':
                // Return the unique string signature for the cuboid (essentially a representation of it's dimensions)
                return $this->signature = sprintf("%gx%gx%g", $this->length, $this->width, $this->height);

            case 'volume':
                // Return the basic volume of the cuboid
                return $this->volume = (float)($this->length * $this->width * $this->height);

            case 'irregularity':
                // Return an arbitrary score defining how far away from a perfect cube the cuboid is.
                $idealEdge = pow($this->volume, 1/3);
                return $this->irregularity = (float)(
                    (($this->length - $idealEdge)**2) +
                    (($this->width  - $idealEdge)**2) +
                    (($this->height - $idealEdge)**2)
                );

            case 'rotations':
                // Return the set of distinct rotations for the cuboid, each as a new cuboid
                if ($this->irregularity < 1e-6) {
                    // it's a cube!
                    return $this->rotations = [clone $this];
                }
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


/**
 * Cuboid class
 *
 * Trivial cuboid structure comprising length, width and height. Other properties added by the CuboidMaterialisedProperties
 * trait.
 */
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


/**
 * RegularisedCuboid class
 *
 * Trivial extension of the Cuboid class that mandates the following dimensional characteristics:
 *
 * Length >= Width >= Height
 *
 */
class RegularisedCuboid extends Cuboid
{
    public function __construct(float $l, float $w, float $h)
    {
        $dim = [$l, $w, $h];
        sort($dim);
        parent::__construct($dim[2], $dim[1], $dim[0]);
    }
}


/**
 * CuboidPacker class
 *
 * Minimalist algorithm for how many of an input Cuboid can be packed into an output cuboid by testing each of the
 * distinct orientations possible.
 *
 */
class CuboidPacker
{
    /**
     * Perform packing test based on trying the different orientation 
     *
     * @param Cuboid $box
     * @param Cuboid $container
     *
     * @return object { int $count, string $rotation, float $efficiency }
     */
    public function best(Cuboid $box, Cuboid $container)
    {
        $bestCount    = 0;
        $rotation     = null;
        foreach ($box->rotations as $rotationType => $rotated) {
            $count = $this->evaluate($rotated, $container);
            if ($count > $bestCount) {
                $bestCount = $count;
                $rotation  = $rotated;
            }
        }

        if (0 === $bestCount) {
            return (object)[
                'count'      => 0,
                'rotation'   => "N/A",
                'efficiency' => 0,
                'block'      => null
            ];
        }

        return (object)[
            'count'      => $bestCount,
            'rotation'   => $rotated->signature,
            'efficiency' => 100*(($bestCount * $rotation->volume) / $container->volume),
            'block'      => new Cuboid(
                $rotation->length * (int)($container->length / $rotation->length),
                $rotation->width  * (int)($container->width  / $rotation->width),
                $rotation->height * (int)($container->height / $rotation->height)
            )
        ];
    }

    private function evaluate(Cuboid $box, Cuboid $container) : int
    {
        return (int)($container->length / $box->length) *
               (int)($container->width  / $box->width) *
               (int)($container->height / $box->height);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * @var Cuboid[] $containers
 */
$containers = [];
for ($i = 0; $i<8; $i++) {
    $containers[] = new Cuboid(mt_rand(40, 80), mt_rand(50, 100), mt_rand(60, 120));
}
$containers[] = new Cuboid(7*17, 5*13, 3*11); // Products of Primes
$containers[] = new Cuboid(30, 40, 50);

/**
 * @var RegularisedCuboid[] $boxes
 */
$boxes = [];
for ($i = 0; $i<8; $i++) {
    $boxes[] = new RegularisedCuboid(mt_rand(1, 30), mt_rand(1, 30), mt_rand(1, 30));
}
$boxes[] = new RegularisedCuboid(7, 5, 3); // Primes
$boxes[] = new RegularisedCuboid(5, 5, 5); // Cube


/**
 * @var CuboidPacker $packer
 */
$packer = new CuboidPacker();

foreach ($containers as $container) {

    echo
        "Testing Container: ", $container,
        " [Volume: ",          $container->volume,
        ", Irregularity: ",    $container->irregularity,
        "]\n";

    foreach ($boxes as $i => $box) {
        $best   = $packer->best($box, $container);
        echo
            "\t", $i,
            " Testing Box: ",       $box,
            " into Container: ",    $container,
            "\n\t\t[Volume: ",      $box->volume,
            ", Irregularity: ",     $box->irregularity,
            "]\n\t\tCount: ",       $best->count,
            "\n\t\tEfficiency: ",   $best->efficiency,
            "%\n\t\tRotation: ",    $best->rotation,
            "\n\t\tPacked Block: ", $best->block,
            "\n";
    }

}

<?php

namespace Tests\Unit;

use App\Models\Release;
use App\Models\ReleaseDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseDependencyModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function release_dependency_belongs_to_upstream_and_downstream_releases()
    {
        // Given: Two releases and a dependency
        $upstreamRelease = Release::factory()->create(['name' => 'API V2']);
        $downstreamRelease = Release::factory()->create(['name' => 'Mobile App V3']);

        $dependency = ReleaseDependency::create([
            'upstream_release_id' => $upstreamRelease->id,
            'downstream_release_id' => $downstreamRelease->id,
            'dependency_type' => 'blocks',
            'description' => 'Mobile app needs API V2'
        ]);

        // When: Accessing relationships
        $upstream = $dependency->upstreamRelease;
        $downstream = $dependency->downstreamRelease;

        // Then: Relationships should be properly defined
        $this->assertInstanceOf(Release::class, $upstream);
        $this->assertEquals($upstreamRelease->id, $upstream->id);
        $this->assertEquals('API V2', $upstream->name);

        $this->assertInstanceOf(Release::class, $downstream);
        $this->assertEquals($downstreamRelease->id, $downstream->id);
        $this->assertEquals('Mobile App V3', $downstream->name);
    }

    /** @test */
    public function release_dependency_validates_dependency_type_enum()
    {
        // Given: Valid dependency types
        $validTypes = ['blocks', 'enables', 'informs'];

        foreach ($validTypes as $type) {
            // When: Creating with valid type
            $dependency = ReleaseDependency::make([
                'upstream_release_id' => 1,
                'downstream_release_id' => 2,
                'dependency_type' => $type,
                'description' => "Test {$type} dependency"
            ]);

            // Then: Type should be accepted
            $this->assertEquals($type, $dependency->dependency_type);
        }
    }

    /** @test */
    public function release_dependency_can_detect_circular_dependencies()
    {
        // Given: A chain of releases A -> B -> C
        $releaseA = Release::factory()->create();
        $releaseB = Release::factory()->create();
        $releaseC = Release::factory()->create();

        ReleaseDependency::create([
            'upstream_release_id' => $releaseA->id,
            'downstream_release_id' => $releaseB->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $releaseB->id,
            'downstream_release_id' => $releaseC->id,
            'dependency_type' => 'blocks'
        ]);

        // When: Checking for circular dependency C -> A
        $wouldCreateCircle = ReleaseDependency::wouldCreateCircularDependency(
            $releaseC->id,
            $releaseA->id
        );

        // Then: Circular dependency should be detected
        $this->assertTrue($wouldCreateCircle);

        // And: Non-circular dependency should not be detected
        $releaseD = Release::factory()->create();
        $wouldNotCreateCircle = ReleaseDependency::wouldCreateCircularDependency(
            $releaseC->id,
            $releaseD->id
        );

        $this->assertFalse($wouldNotCreateCircle);
    }

    /** @test */
    public function release_dependency_can_find_affected_downstream_releases()
    {
        // Given: A dependency chain
        $upstreamRelease = Release::factory()->create();
        $directDownstream = Release::factory()->create();
        $indirectDownstream = Release::factory()->create();
        $unrelatedRelease = Release::factory()->create();

        ReleaseDependency::create([
            'upstream_release_id' => $upstreamRelease->id,
            'downstream_release_id' => $directDownstream->id,
            'dependency_type' => 'blocks'
        ]);

        ReleaseDependency::create([
            'upstream_release_id' => $directDownstream->id,
            'downstream_release_id' => $indirectDownstream->id,
            'dependency_type' => 'blocks'
        ]);

        // When: Finding affected downstream releases
        $affectedReleases = ReleaseDependency::findAffectedDownstreamReleases($upstreamRelease->id);

        // Then: Both direct and indirect downstream releases should be found
        $affectedIds = $affectedReleases->pluck('id')->toArray();
        $this->assertContains($directDownstream->id, $affectedIds);
        $this->assertContains($indirectDownstream->id, $affectedIds);
        $this->assertNotContains($unrelatedRelease->id, $affectedIds);
    }

    /** @test */
    public function release_dependency_calculates_impact_severity_based_on_type()
    {
        // Given: Different dependency types
        $upstreamRelease = Release::factory()->create();
        $downstreamRelease = Release::factory()->create();

        // When: Creating blocking dependency
        $blockingDep = ReleaseDependency::make([
            'upstream_release_id' => $upstreamRelease->id,
            'downstream_release_id' => $downstreamRelease->id,
            'dependency_type' => 'blocks'
        ]);

        // Then: Impact severity should be high
        $this->assertEquals('high', $blockingDep->getImpactSeverity());

        // When: Creating enabling dependency
        $enablingDep = ReleaseDependency::make([
            'upstream_release_id' => $upstreamRelease->id,
            'downstream_release_id' => $downstreamRelease->id,
            'dependency_type' => 'enables'
        ]);

        // Then: Impact severity should be medium
        $this->assertEquals('medium', $enablingDep->getImpactSeverity());

        // When: Creating informing dependency
        $informingDep = ReleaseDependency::make([
            'upstream_release_id' => $upstreamRelease->id,
            'downstream_release_id' => $downstreamRelease->id,
            'dependency_type' => 'informs'
        ]);

        // Then: Impact severity should be low
        $this->assertEquals('low', $informingDep->getImpactSeverity());
    }
}
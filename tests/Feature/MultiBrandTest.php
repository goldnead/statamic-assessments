<?php

namespace Goldnead\Assessments\Tests\Feature;

use Closure;
use Goldnead\Assessments\Models\Assessment;
use Goldnead\Assessments\Models\Response;
use Goldnead\Assessments\Tests\TestCase;
use Goldnead\BrandContext\Models\Brand;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;

/**
 * Multi-brand, which the default run never exercises: every scope is a no-op
 * in single-brand mode. The public routes carry no session, so no brand is
 * current and the fail-closed scope would hide the very assessment the URL
 * names — each route derives it from the handle.
 */
class MultiBrandTest extends TestCase
{
    protected Brand $alpha;

    protected Brand $beta;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('brand-context.multi_brand', true);

        $this->alpha = Brand::query()->firstOrCreate(['handle' => 'alpha'], ['name' => 'Alpha']);
        $this->beta = Brand::query()->firstOrCreate(['handle' => 'beta'], ['name' => 'Beta']);
    }

    protected function tearDown(): void
    {
        app('brand-context')->forget();

        parent::tearDown();
    }

    protected function inBrand(Brand $brand, Closure $callback): mixed
    {
        return app('brand-context')->runFor($brand, $callback);
    }

    #[Test]
    public function assessments_and_responses_are_scoped_to_their_brand(): void
    {
        $a = $this->inBrand($this->alpha, fn () => $this->makeAssessment(['handle' => 'alpha_check']));
        $b = $this->inBrand($this->beta, fn () => $this->makeAssessment(['handle' => 'beta_check']));

        $this->assertSame($this->alpha->id, $a->brand_id);
        $this->assertSame($this->beta->id, $b->brand_id);

        $this->inBrand($this->alpha, function () {
            $this->assertSame(['alpha_check'], Assessment::query()->pluck('handle')->all());
        });

        $this->inBrand($this->beta, function () {
            $this->assertSame(['beta_check'], Assessment::query()->pluck('handle')->all());
        });
    }

    #[Test]
    public function the_public_routes_derive_the_brand_from_the_handle(): void
    {
        $assessment = $this->inBrand($this->beta, fn () => $this->makeAssessment(['handle' => 'beta_check']));
        $answers = $this->inBrand($this->beta, fn () => $this->validAnswers($assessment));

        app('brand-context')->forget();

        $this->get('/a/beta_check')->assertOk()->assertSee('Stimm-Check');

        $this->post('/a/beta_check/submit', [
            'email' => 'sing@example.com',
            'answers' => $answers,
        ])->assertRedirectContains('/a/beta_check/r/');

        $response = Response::query()->withoutGlobalScopes()->sole();

        $this->assertSame($this->beta->id, $response->brand_id);

        app('brand-context')->forget();

        $this->get('/a/beta_check/r/'.$response->visit_token)->assertOk()->assertSee('Weit');
    }

    #[Test]
    public function a_handle_is_unique_across_brands(): void
    {
        $this->inBrand($this->alpha, fn () => $this->makeAssessment(['handle' => 'same']));

        $this->expectException(QueryException::class);

        $this->inBrand($this->beta, fn () => $this->makeAssessment(['handle' => 'same']));
    }
}

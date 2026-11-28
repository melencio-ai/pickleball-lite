<?php
namespace Tests\Feature;
use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_exact_approved_vector_brand_assets_are_present(): void
    {
        foreach([
            'brand/pickle-lite-horizontal-primary.svg'=>'da1195a92b8f9a4f5feef481058866432a6baceeeb13db1812e2ffc9bff2f157',
            'brand/pickle-lite-horizontal-reverse.svg'=>'982ee2fa0057b2eb884d68ddced51d0ddd179622f284f011bd0c30399ecd9007',
            'brand/pickle-lite-icon-color.svg'=>'e5c75cc13308a97152b126e030efb1f91c036f90854160f412f8ab9df7a298ff',
            'favicon.svg'=>'45316441e42a459bf432e23eaac186e905d94e175ae9e36cc9db60c14cc8772b',
        ] as $path=>$hash) {
            $this->assertFileExists(public_path($path));
            $this->assertSame($hash,hash_file('sha256',public_path($path)),$path);
        }
    }
}

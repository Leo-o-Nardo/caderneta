<?php

/*
| As páginas públicas que as lojas pedem: a política de privacidade e a exclusão da conta, ligadas
| no rodapé de todas as páginas do servidor.
*/

it('shows the privacy policy with who answers for the data and the contact', function () {
    config(['access.privacy_controller' => 'Leonardo Ferreira', 'access.contact_email' => 'contato@example.com']);

    $this->get('/privacidade')
        ->assertOk()
        ->assertSee('Política de privacidade')
        ->assertSee('mantida por Leonardo Ferreira')
        ->assertSee('mailto:contato@example.com', false)
        ->assertSee(route('account.delete'), false);
});

it('links both pages in the footer of the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('privacy'), false)
        ->assertSee(route('account.delete'), false);
});

<?php

it('redireciona a home para a consulta', function () {
    $this->get(route('home'))->assertRedirect('/consulta');
});

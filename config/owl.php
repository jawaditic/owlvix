<?php

// Owl voice lines: text, audio file (public/audio) and lip-sync data.
// Lip-sync = loudness of the clip, 30 steps a second, 0 (silent) to 9 (loud); it moves the beak.
return [
    'intro' => [
        ['text' => 'Hoo-hoo! Welcome to Owlvix.', 'file' => 'audio/intro-1.mp3', 'lipsync' => '00000787698774210000000000000259994138762000138666769741111000000000000000000000'],
        ['text' => 'We design and build web apps, mobile apps and smart AI tools.', 'file' => 'audio/intro-2.mp3', 'lipsync' => '000199848975499899897543113332000566532225555323222211000110000000002579995598664322110011010000000004520111136688765544333333320011111110000000000000000000'],
        ['text' => 'From your first idea to launch day, we handle everything.', 'file' => 'audio/intro-3.mp3', 'lipsync' => '00000687895100177861009876314787872013533335555653220033221110100000000000000024999878998755444311110000100000000000000'],
        ['text' => 'Tell us what you need, and get a free quote today!', 'file' => 'audio/intro-4.mp3', 'lipsync' => '000019999311542367898868864321000000000000014424955410000000036886521112898520015244222111100000000000'],
    ],
    'quote' => [
        ['text' => 'Hoo-hoo! Welcome to Owlvix.', 'file' => 'audio/quote-1.mp3', 'lipsync' => '00000787698874210000000000000259994138762000138666769741111000100000000000000000'],
        ['text' => 'Need a website, an app, or some AI magic?', 'file' => 'audio/quote-2.mp3', 'lipsync' => '0004998995699831112532211000000000000144444578876100000000001531179888777777656655565111100000000000000'],
        ['text' => 'Tell me about your project, and get a free quote within one day!', 'file' => 'audio/quote-3.mp3', 'lipsync' => '00000199967778539974242012997852467620000000000000002673875410000003776411111377865310000001561006888997556655541333211110000000000000'],
    ],
    'thanks' => [
        ['text' => 'Hoo-hoo! Thank you for your request.', 'file' => 'audio/thanks-1.mp3', 'lipsync' => '00000898466673100000000000001999610686117866554011153321100000000000000000'],
        ['text' => 'Our team is already taking a look at your project.', 'file' => 'audio/thanks-2.mp3', 'lipsync' => '0001699610149768788874100000463499988465201596116654335401222101343210111100000000000000'],
        ['text' => 'You\'ll hear back from us within one business day. Talk soon!', 'file' => 'audio/thanks-3.mp3', 'lipsync' => '00006999888794399888300366665510124300066665433356666324321022100001211111000000000000001675100004986432100000000000'],
    ],
];

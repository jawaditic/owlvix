@extends('emails.layout')

@section('preheader', 'Your code to see your orders at '.$store->name.'.')
@section('eyebrow', 'Your orders 🔐')
@section('headline', $code)
@section('subline', 'Type this code to see your orders at '.$store->name.'. It works for 15 minutes.')

@section('body')
    <p style="margin:0;font-size:14px;color:#64748b;">Didn’t ask for it? You can ignore this email — nobody can see your orders without the code.</p>
@endsection

@section('footer_note', 'You get this because someone asked to see the orders placed with this email at '.$store->name.', a shop made with Owlvix.')

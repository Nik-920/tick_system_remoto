@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 16px; color:#111827; font-size:18px;">{{ $title }}</h1>

    @foreach ($bodyLines as $line)
        <p style="margin:0 0 12px; color:#374151; font-size:14px; line-height:1.5;">{{ $line }}</p>
    @endforeach

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px;">
        <tr>
            <td style="border-radius:6px; background-color:#2563eb;">
                <a href="{{ $ctaUrl }}" style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none;">
                    {{ $ctaLabel }}
                </a>
            </td>
        </tr>
    </table>
@endsection

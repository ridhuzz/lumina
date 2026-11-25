<?php

namespace App\Support;

/**
 * Membuat gambar banner CONTOH (PNG) untuk data demo: gradien, ornamen lingkaran,
 * judul, subjudul, dan tombol. Di produksi, admin mengunggah gambar desainnya sendiri.
 * Butuh ekstensi GD + FreeType; font Plus Jakarta Sans (OFL) di resources/fonts.
 */
class DemoBannerImage
{
    public static function make(
        int $w,
        int $h,
        string $title,
        string $subtitle,
        string $cta,
        array $from,
        array $to,
    ): string {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);

        // Gradien diagonal.
        for ($x = 0; $x < $w; $x++) {
            $t = $x / max(1, $w - 1);
            $c = imagecolorallocate($img,
                (int) ($from[0] + ($to[0] - $from[0]) * $t),
                (int) ($from[1] + ($to[1] - $from[1]) * $t),
                (int) ($from[2] + ($to[2] - $from[2]) * $t));
            imageline($img, $x, 0, $x, $h, $c);
        }

        // Ornamen lingkaran transparan.
        $white = fn (int $alpha) => imagecolorallocatealpha($img, 255, 255, 255, $alpha);
        imagefilledellipse($img, (int) ($w * 0.86), (int) ($h * 0.1), (int) ($h * 1.3), (int) ($h * 1.3), $white(108));
        imagefilledellipse($img, (int) ($w * 0.72), (int) ($h * 1.05), (int) ($h * 0.9), (int) ($h * 0.9), $white(112));
        imagefilledellipse($img, (int) ($w * 0.93), (int) ($h * 0.78), (int) ($h * 0.32), (int) ($h * 0.32), imagecolorallocatealpha($img, 255, 213, 79, 70));

        $bold = resource_path('fonts/PlusJakartaSans-Bold.ttf');
        $medium = resource_path('fonts/PlusJakartaSans-Medium.ttf');
        $pad = (int) ($w * 0.055);
        $wide = $h / $w < 0.3; // banner beranda: tipis & lebar

        $titleSize = $wide ? $h * 0.16 : $h * 0.12;
        $subSize = $wide ? $h * 0.09 : $h * 0.06;
        $y = $wide ? (int) ($h * 0.34) : (int) ($h * 0.36);

        imagettftext($img, $titleSize, 0, $pad, $y, $white(0), $bold, $title);
        $y += (int) ($subSize * 2.1);
        imagettftext($img, $subSize, 0, $pad, $y, $white(25), $medium, $subtitle);

        // Tombol CTA kuning.
        $ctaSize = $wide ? $h * 0.085 : $h * 0.05;
        $box = imagettfbbox($ctaSize, 0, $bold, $cta);
        $bw = (int) ($box[2] - $box[0] + $ctaSize * 2.6);
        $bh = (int) ($ctaSize * 2.4);
        $by = $y + (int) ($subSize * ($wide ? 0.9 : 1.4));
        $yellow = imagecolorallocate($img, 255, 193, 7);
        imagefilledrectangle($img, $pad + (int) ($bh / 2), $by, $pad + $bw - (int) ($bh / 2), $by + $bh, $yellow);
        imagefilledellipse($img, $pad + (int) ($bh / 2), $by + (int) ($bh / 2), $bh, $bh, $yellow);
        imagefilledellipse($img, $pad + $bw - (int) ($bh / 2), $by + (int) ($bh / 2), $bh, $bh, $yellow);
        imagettftext($img, $ctaSize, 0, $pad + (int) ($ctaSize * 1.3), $by + (int) ($bh / 2 + $ctaSize / 2),
            imagecolorallocate($img, 23, 69, 127), $bold, $cta);

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}

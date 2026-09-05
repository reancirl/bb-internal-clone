import { ImgHTMLAttributes } from 'react';

/**
 * The brand mark. `/img/logo.png` is the 17716×9644 master and must never be
 * rendered directly — the browser downloads ~1 MB and decodes 171 MP to fill a
 * slot a few hundred pixels wide at most. These derivatives are generated from it.
 *
 * The artwork is white, so `tone="dark"` serves a navy recolor for use on light
 * backgrounds.
 */
export default function AppLogoIcon({ className, tone = 'light', ...props }: ImgHTMLAttributes<HTMLImageElement> & { tone?: 'light' | 'dark' }) {
    const base = tone === 'dark' ? '/img/logo-dark' : '/img/logo';
    const srcSet =
        tone === 'dark' ? `${base}-360.png 360w, ${base}-720.png 720w` : `${base}-360.png 360w, ${base}-720.png 720w, ${base}-1080.png 1080w`;

    return (
        <img
            src={`${base}-720.png`}
            srcSet={srcSet}
            sizes="(min-width: 1440px) 480px, (min-width: 1024px) 424px, 240px"
            width={1080}
            height={588}
            alt="BuffaloBuilt Internal"
            decoding="async"
            className={`object-contain ${className ?? ''}`}
            {...props}
        />
    );
}

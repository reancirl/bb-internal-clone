import { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/img/logo.png"
            alt="BuffaloBuilt Internal"
            className={`h-full w-auto object-contain ${className ?? ''}`}
            {...props}
        />
    );
}

import logo from "../assets/app-logo.svg";

export function Logo({ size = 40, glow = false, className = "" }: { size?: number; glow?: boolean; className?: string }) {
  return (
    <img
      src={logo}
      width={size}
      height={size}
      alt="Element.Lax"
      draggable={false}
      className={(glow ? "pulse-glow " : "") + className}
      style={{ width: size, height: size }}
    />
  );
}

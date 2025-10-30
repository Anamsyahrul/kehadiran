"""Firmware microSD configuration helper.

Usage example:
    python generate_sd_config.py --device esp32-01 --server http://192.168.1.10/kehadiran/web/api \
        --wifi "SSID" "PASSWORD" --secret supersecret --out sd_config --registration --students

This generates:
  - config.txt         -> key/value configuration consumed by the firmware
  - students.csv       -> optional placeholder (useful for OLED name display)
  - register.mode      -> optional flag to enable registration mode

Copy the files into the root of the microSD card before inserting into the ESP32.
"""

from __future__ import annotations
import argparse
from pathlib import Path


DEFAULTS = {
    "DEVICE_ID": "esp32-01",
    "DEVICE_SECRET": "changeme_device_secret",
    "API_BASE": "http://192.168.1.10/kehadiran/web/api",
    "WIFI_SSID": "YOUR_SSID",
    "WIFI_PASS": "YOUR_PASSWORD",
    "SCAN_DEBOUNCE_MS": 2000,
    "BATCH_SIZE": 20,
    "ALLOW_OFFLINE": True,
}


def build_config(device_id: str,
                 secret: str,
                 api_base: str,
                 ssid: str,
                 password: str,
                 allow_offline: bool,
                 debounce_ms: int,
                 batch_size: int) -> str:
    cfg = {
        "DEVICE_ID": device_id,
        "DEVICE_SECRET": secret,
        "API_BASE": api_base.rstrip('/'),
        "WIFI_SSID": ssid,
        "WIFI_PASS": password,
        "ALLOW_OFFLINE": "true" if allow_offline else "false",
        "SCAN_DEBOUNCE_MS": str(debounce_ms),
        "BATCH_SIZE": str(batch_size),
    }
    return "\n".join(f"{key}={value}" for key, value in cfg.items()) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser(description="Generate microSD config files for firmware")
    parser.add_argument("--device", default=DEFAULTS["DEVICE_ID"], help="Device ID sesuai dashboard")
    parser.add_argument("--secret", default=DEFAULTS["DEVICE_SECRET"], help="Device secret sesuai dashboard")
    parser.add_argument("--server", default=DEFAULTS["API_BASE"], help="API base URL (akhiri dengan /kehadiran/web/api)")
    parser.add_argument("--wifi", nargs=2, metavar=("SSID", "PASS"), default=(DEFAULTS["WIFI_SSID"], DEFAULTS["WIFI_PASS"]))
    parser.add_argument("--debounce", type=int, default=DEFAULTS["SCAN_DEBOUNCE_MS"], help="Debounce scan (ms)")
    parser.add_argument("--batch", type=int, default=DEFAULTS["BATCH_SIZE"], help="Jumlah event per batch upload")
    parser.add_argument("--no-offline", action="store_true", help="Nonaktifkan penyimpanan offline (tidak disarankan)")
    parser.add_argument("--registration", action="store_true", help="Buat file register.mode untuk mode registrasi")
    parser.add_argument("--students", action="store_true", help="Generate students.csv placeholder")
    parser.add_argument("--out", default="sd_config", help="Folder output")

    args = parser.parse_args()

    output_dir = Path(args.out).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    cfg_text = build_config(
        device_id=args.device,
        secret=args.secret,
        api_base=args.server,
        ssid=args.wifi[0],
        password=args.wifi[1],
        allow_offline=not args.no_offline,
        debounce_ms=args.debounce,
        batch_size=args.batch,
    )
    (output_dir / "config.txt").write_text(cfg_text, encoding="utf-8")

    if args.students:
        (output_dir / "students.csv").write_text("uid_hex,name,kelas\n", encoding="utf-8")

    if args.registration:
        (output_dir / "register.mode").write_text("", encoding="utf-8")

    print(f"Konfigurasi microSD telah dibuat di: {output_dir}")


if __name__ == "__main__":
    main()

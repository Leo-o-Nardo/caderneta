import {
  Baby,
  Bike,
  BookOpen,
  BriefcaseBusiness,
  Car,
  Circle,
  Coffee,
  Droplet,
  Dumbbell,
  Ellipsis,
  Fuel,
  Gamepad2,
  Gift,
  HeartPulse,
  House,
  Landmark,
  List,
  PawPrint,
  PiggyBank,
  Pill,
  Plane,
  Repeat,
  Scissors,
  Shield,
  Shirt,
  ShoppingBag,
  ShoppingCart,
  Smartphone,
  Ticket,
  TrendingUp,
  Utensils,
  Wallet,
  Wrench,
  Zap,
  type LucideIcon,
} from 'lucide-react-native';
import { View } from 'react-native';

import { radius } from '@/theme/tokens';

/** As chaves de ícone que a API usa (Category::ICONS e ListKind no backend) e o desenho de cada. */
const ICONS: Record<string, LucideIcon> = {
  cart: ShoppingCart,
  utensils: Utensils,
  coffee: Coffee,
  bike: Bike,
  car: Car,
  fuel: Fuel,
  home: House,
  bolt: Zap,
  droplet: Droplet,
  phone: Smartphone,
  heart: HeartPulse,
  pill: Pill,
  ticket: Ticket,
  gamepad: Gamepad2,
  plane: Plane,
  bag: ShoppingBag,
  shirt: Shirt,
  scissors: Scissors,
  repeat: Repeat,
  book: BookOpen,
  baby: Baby,
  paw: PawPrint,
  gift: Gift,
  dumbbell: Dumbbell,
  wrench: Wrench,
  shield: Shield,
  landmark: Landmark,
  wallet: Wallet,
  briefcase: BriefcaseBusiness,
  'trending-up': TrendingUp,
  'piggy-bank': PiggyBank,
  dots: Ellipsis,
  // Só das listas.
  list: List,
};

/**
 * Os ícones que uma categoria pode ter, na mesma ordem do Category::ICONS do backend (que recusa
 * qualquer outro). Mudou lá, muda aqui.
 */
export const CATEGORY_ICONS = [
  'cart', 'utensils', 'coffee', 'bike', 'car', 'fuel', 'home', 'bolt',
  'droplet', 'phone', 'heart', 'pill', 'ticket', 'gamepad', 'plane', 'bag',
  'shirt', 'scissors', 'repeat', 'book', 'baby', 'paw', 'gift', 'dumbbell',
  'wrench', 'shield', 'landmark', 'wallet', 'briefcase', 'trending-up', 'piggy-bank', 'dots',
] as const;

type Props = {
  icon: string;
  color: string;
  size?: number;
};

/**
 * Só o desenho de uma chave, sem o fundo. Chave desconhecida vira um círculo em vez de quebrar a
 * tela. A busca fica direto no mapa (e não numa função que devolve o componente) porque a regra
 * do React Compiler não deixa criar componente durante o render.
 */
export function IconGlyph({ icon, color, size = 20 }: Props) {
  const Icon = ICONS[icon] ?? Circle;

  return <Icon size={size} color={color} strokeWidth={1.6} />;
}

/**
 * O ícone da categoria do guia (parte 04): o desenho fino na cor da categoria, sem quadrado atrás.
 * O tamanho é o da caixa onde ele fica centralizado: 24 (o padrão) é a coluna fixa das linhas, com
 * o desenho de 20.
 */
export function CategoryIcon({ icon, color, size = 24 }: Props) {
  return (
    <View style={{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }}>
      <IconGlyph icon={icon} size={Math.round(size * 0.84)} color={color} />
    </View>
  );
}

/** O quadradinho de cor da legenda de "Quem gastou" (8 px, canto 2), ao lado do nome da pessoa. */
export function CategoryMark({ color }: { color: string }) {
  return <View style={{ width: 8, height: 8, borderRadius: radius.xs, backgroundColor: color }} />;
}

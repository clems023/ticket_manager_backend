<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\TicketPriority;
use App\Entity\TicketStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    private const ALLOWED_SORT = ['createdAt', 'priority'];
    private const ALLOWED_ORDER = ['asc', 'desc'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @return array{0: list<Ticket>, 1: int}
     */
    public function findPaginated(
        ?TicketStatus $status = null,
        ?TicketPriority $priority = null,
        string $sort = 'createdAt',
        string $order = 'desc',
        int $page = 1,
        int $limit = 20,
    ): array {
        $sort = \in_array($sort, self::ALLOWED_SORT, true) ? $sort : 'createdAt';
        $order = \in_array(strtolower($order), self::ALLOWED_ORDER, true) ? strtoupper($order) : 'DESC';
        $limit = min(max(1, $limit), 100);
        $offset = max(0, ($page - 1) * $limit);

        $qb = $this->createQueryBuilder('t')
            ->select('t');

        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }
        if (null !== $priority) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $priority);
        }

        $qb->orderBy('t.' . $sort, $order);

        $total = (int) (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        $tickets = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [$tickets, $total];
    }
}
